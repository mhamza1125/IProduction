<?php

namespace App\Modules\Finance\Livewire\Receipts;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Services\Receipts;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Receipt $receipt = null;

    #[Url(as: 'customer', except: null)]
    public ?int $customer_id = null;

    public string $receipt_date = '';

    public string $amount = '';

    public ?int $money_account_id = null;

    public string $reference = '';

    public string $notes = '';

    /** @var list<array{gl_account_id: int|string|null, amount: string, memo: string}> */
    public array $deductions = [];

    /** @var array<string, string> "type:id" => amount */
    public array $allocations = [];

    public function mount(?Receipt $receipt = null): void
    {
        if ($receipt?->exists) {
            abort_unless($receipt->isDraft(), 403, 'Only drafts can be edited.');
            $this->receipt = $receipt;
            $this->fill($receipt->only(['customer_id', 'money_account_id']));
            $this->receipt_date = $receipt->receipt_date->format('Y-m-d');
            $this->amount = (string) $receipt->amount;
            $this->reference = (string) $receipt->reference;
            $this->notes = (string) $receipt->notes;
            $this->deductions = $receipt->deductions->map(fn ($d) => ['gl_account_id' => $d->gl_account_id, 'amount' => (string) $d->amount, 'memo' => (string) $d->memo])->all();
            foreach ($receipt->planned_allocations ?? [] as $a) {
                $this->allocations[$a['type'].':'.$a['id']] = (string) $a['amount'];
            }

            return;
        }
        $this->receipt_date = now()->format('Y-m-d');
    }

    public function addDeduction(): void
    {
        $this->deductions[] = ['gl_account_id' => null, 'amount' => '', 'memo' => ''];
    }

    public function removeDeduction(int $i): void
    {
        unset($this->deductions[$i]);
        $this->deductions = array_values($this->deductions);
    }

    public function updatedCustomerId(): void
    {
        $this->allocations = [];
        $this->money_account_id = null;
    }

    public function save(Receipts $receipts): void
    {
        $this->authorize($this->receipt ? 'receipts.edit' : 'receipts.create');
        $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'receipt_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'money_account_id' => ['required', 'exists:money_accounts,id'],
            'deductions.*.gl_account_id' => ['required', 'exists:gl_accounts,id'],
            'deductions.*.amount' => ['required', 'numeric', 'gt:0'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ]);
        $alloc = [];
        foreach ($this->allocations as $key => $amount) {
            if ($amount !== '' && (float) $amount > 0) {
                [$type, $id] = explode(':', $key);
                $alloc[] = ['type' => $type, 'id' => (int) $id, 'amount' => (string) $amount];
            }
        }
        try {
            $receipt = $receipts->save($this->receipt, ['receipt_date' => $this->receipt_date, 'customer_id' => $this->customer_id, 'amount' => $this->amount,
                'money_account_id' => $this->money_account_id, 'reference' => $this->reference, 'notes' => $this->notes], $this->deductions, $alloc);
        } catch (DocumentException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$receipt->number} saved.");
        $this->redirectRoute('finance.receipts.show', $receipt, navigate: true);
    }

    public function render(Receipts $receipts): View
    {
        $customer = $this->customer_id ? Customer::query()->find($this->customer_id) : null;
        $deducted = Decimal::sum(array_map(fn ($d) => is_numeric($d['amount']) ? (string) $d['amount'] : '0', $this->deductions));

        return view('finance::livewire.receipts.form', [
            'customers' => Customer::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($c) => [$c->id => $c->label().' ('.$c->currency_code.')']),
            'customer' => $customer,
            'accounts' => MoneyAccount::query()->active()->where('currency_code', $customer->currency_code ?? 'none')->pluck('name', 'id'),
            'heads' => GlAccount::query()->active()->whereIn('type', ['expense', 'cost'])->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' '.$a->name]),
            'invoices' => $customer ? $receipts->openFor($customer)->filter(fn ($i) => $i->receivableCurrency() === $customer->currency_code) : collect(),
            'net' => is_numeric($this->amount) ? Decimal::sub((string) $this->amount, $deducted) : null,
        ])->title($this->receipt ? 'Edit '.$this->receipt->number : 'New customer receipt');
    }
}
