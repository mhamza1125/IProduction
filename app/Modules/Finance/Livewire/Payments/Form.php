<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\Payables;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Draft payment to a vendor, contractor, employee or expense head, with
 * optional allocations to open bills / jobs / payrolls.
 */
class Form extends Component
{
    public const PAYEES = ['vendor' => Vendor::class, 'contractor' => Contractor::class, 'employee' => Employee::class];

    #[Locked]
    public ?Payment $payment = null;

    #[Url(as: 'kind')]
    public string $payee_kind = 'vendor';

    #[Url(as: 'payee')]
    public ?int $payee_id = null;

    public ?int $expense_account_id = null;

    public ?int $money_account_id = null;

    public ?int $payment_method_id = null;

    public ?string $payment_date = null;

    public string $reference = '';

    public string $amount = '';

    public string $notes = '';

    /** @var array<string, string> "type:id" => amount */
    public array $allocations = [];

    public function mount(?Payment $payment = null): void
    {
        if ($payment?->exists) {
            abort_unless($payment->isDraft(), 403, 'Only drafts can be edited.');
            $this->payment = $payment;
            $this->payee_kind = $payment->payee_kind;
            $this->payee_id = $payment->payee_id;
            $this->expense_account_id = $payment->expense_account_id;
            $this->money_account_id = $payment->money_account_id;
            $this->payment_method_id = $payment->payment_method_id;
            $this->payment_date = $payment->payment_date->format('Y-m-d');
            $this->reference = (string) $payment->reference;
            $this->amount = (string) $payment->amount;
            $this->notes = (string) $payment->notes;
            foreach ($payment->planned_allocations ?? [] as $a) {
                $this->allocations[$a['type'].':'.$a['id']] = (string) $a['amount'];
            }
        } else {
            $this->payment_date = now()->format('Y-m-d');
            $this->money_account_id = MoneyAccount::cashBook()->id;
        }
    }

    public function updatedPayeeKind(): void
    {
        $this->payee_id = null;
        $this->allocations = [];
    }

    public function updatedPayeeId(): void
    {
        $this->allocations = [];
    }

    private function payee(): ?Model
    {
        $class = self::PAYEES[$this->payee_kind] ?? null;

        return $class && $this->payee_id ? $class::query()->find($this->payee_id) : null;
    }

    public function payAllOpen(Payables $payables): void
    {
        $payee = $this->payee();
        if (! $payee) {
            return;
        }
        $total = '0';
        foreach ($payables->openFor($payee) as $p) {
            $this->allocations[$p->getMorphClass().':'.$p->getKey()] = $p->payableBalance();
            $total = Decimal::add($total, $p->payableBalance());
        }
        $this->amount = $total;
    }

    public function save(): void
    {
        $this->authorize($this->payment ? 'payments.edit' : 'payments.create');
        $isExpense = $this->payee_kind === 'expense';

        $data = $this->validate([
            'payee_kind' => ['required', Rule::in(array_keys(Payment::KINDS))],
            'payee_id' => [Rule::requiredIf(! $isExpense), 'nullable', 'integer'],
            'expense_account_id' => [Rule::requiredIf($isExpense), 'nullable', Rule::exists('gl_accounts', 'id')->where('type', 'expense')->where('is_active', true)],
            'money_account_id' => ['required', Rule::exists('money_accounts', 'id')->where('is_active', true)->where('currency_code', 'PKR')],
            'payment_method_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'payment_method')],
            'payment_date' => ['required', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,2'],
        ], ['money_account_id.exists' => 'Choose an active PKR cash or bank account.'], ['payee_id' => 'payee', 'money_account_id' => 'account']);

        $payee = $isExpense ? null : $this->payee();
        if (! $isExpense && (! $payee || ! $payee->getAttribute('is_active'))) {
            $this->addError('payee_id', 'Choose an active payee.');

            return;
        }

        $planned = [];
        $allocated = '0';
        foreach ($this->allocations as $key => $value) {
            if (! is_numeric($value) || Decimal::compare($value, '0') <= 0) {
                continue;
            }
            [$type, $id] = explode(':', $key);
            $planned[] = ['type' => $type, 'id' => (int) $id, 'amount' => Decimal::round($value)];
            $allocated = Decimal::add($allocated, $value);
        }

        if ($isExpense) {
            $planned = [];
        } elseif (Decimal::compare($allocated, $data['amount']) > 0) {
            $this->addError('amount', 'Allocations are more than the payment amount.');

            return;
        }

        $payment = DB::transaction(function () use ($data, $payee, $planned) {
            $payment = $this->payment ?? new Payment(['number' => app(DocumentNumber::class)->next('payment', Carbon::parse($data['payment_date'])), 'created_by' => auth()->id()]);
            $payment->fill([
                'payment_date' => $data['payment_date'],
                'payee_kind' => $data['payee_kind'],
                'payee_type' => $payee?->getMorphClass(),
                'payee_id' => $payee?->getKey(),
                'expense_account_id' => $data['payee_kind'] === 'expense' ? $data['expense_account_id'] : null,
                'money_account_id' => $data['money_account_id'],
                'payment_method_id' => $data['payment_method_id'] ?: null,
                'reference' => $data['reference'] ?: null,
                'amount' => $data['amount'],
                'notes' => $data['notes'] ?: null,
                'planned_allocations' => $planned,
            ])->save();

            return $payment;
        });

        session()->flash('toast', "{$payment->number} saved as draft.");
        $this->redirectRoute('finance.payments.show', $payment, navigate: true);
    }

    public function render(Payables $payables): View
    {
        $class = self::PAYEES[$this->payee_kind] ?? null;
        $payee = $this->payee();

        return view('finance::livewire.payments.form', [
            'kinds' => Payment::KINDS,
            'payees' => $class ? $class::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->label()]) : collect(),
            'expenseAccounts' => GlAccount::query()->active()->where('type', 'expense')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} {$a->name}"]),
            'accounts' => MoneyAccount::query()->active()->where('currency_code', 'PKR')->orderBy('name')->pluck('name', 'id'),
            'methods' => Lookup::options('payment_method', $this->payment_method_id),
            'open' => $payee ? $payables->openFor($payee) : collect(),
        ])->title($this->payment ? 'Edit '.$this->payment->number : 'New payment');
    }
}
