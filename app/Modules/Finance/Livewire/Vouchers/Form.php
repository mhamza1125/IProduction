<?php

namespace App\Modules\Finance\Livewire\Vouchers;

use App\Modules\Core\Models\Currency;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalVoucher;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?JournalVoucher $voucher = null;

    public string $voucher_date = '';

    public string $currency_code = 'PKR';

    public string $description = '';

    /** @var list<array{account: string, party: string, debit: string, credit: string, memo: string, deduct_from_payroll: bool}> */
    public array $lines = [];

    public function mount(?JournalVoucher $voucher = null): void
    {
        if ($voucher?->exists) {
            abort_unless($voucher->isDraft(), 403, 'Only drafts can be edited.');
            $this->voucher = $voucher;
            $this->voucher_date = $voucher->voucher_date->format('Y-m-d');
            $this->currency_code = $voucher->currency_code;
            $this->description = $voucher->description;
            $this->lines = $voucher->lines->map(fn ($l) => [
                'account' => $l->money_account_id ? 'money:'.$l->money_account_id : 'gl:'.$l->gl_account_id,
                'party' => $l->party_type ? $l->party_type.':'.$l->party_id : '', 'debit' => (float) $l->debit ? (string) $l->debit : '',
                'credit' => (float) $l->credit ? (string) $l->credit : '', 'memo' => (string) $l->memo, 'deduct_from_payroll' => $l->deduct_from_payroll,
            ])->all();

            return;
        }
        $this->voucher_date = now()->format('Y-m-d');
        $this->addLine();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['account' => '', 'party' => '', 'debit' => '', 'credit' => '', 'memo' => '', 'deduct_from_payroll' => false];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function save(MoneyMovements $moves): void
    {
        $this->authorize($this->voucher ? 'journal_vouchers.edit' : 'journal_vouchers.create');
        $this->validate([
            'voucher_date' => ['required', 'date'], 'currency_code' => ['required', 'exists:currencies,code'], 'description' => ['required', 'string', 'max:500'],
            'lines.*.account' => ['nullable', 'string'], 'lines.*.debit' => ['nullable', 'numeric', 'min:0'], 'lines.*.credit' => ['nullable', 'numeric', 'min:0'],
        ]);
        try {
            $doc = $moves->saveVoucher($this->voucher, ['voucher_date' => $this->voucher_date, 'currency_code' => $this->currency_code, 'description' => $this->description],
                array_values(array_filter($this->lines, fn ($l) => $l['account'] !== '' || $l['debit'] !== '' || $l['credit'] !== '')));
        } catch (DocumentException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$doc->number} saved.");
        $this->redirectRoute('finance.vouchers.show', $doc, navigate: true);
    }

    public function render(): View
    {
        $accounts = collect();
        foreach (MoneyAccount::query()->active()->where('currency_code', $this->currency_code)->orderBy('name')->get() as $m) {
            $accounts['money:'.$m->id] = 'Cash/bank · '.$m->name;
        }
        foreach (GlAccount::query()->active()->orderBy('code')->get() as $g) {
            $accounts['gl:'.$g->id] = $g->code.' '.$g->name;
        }
        $parties = collect();
        foreach ([Customer::class => 'Customer', Vendor::class => 'Vendor', Employee::class => 'Employee', Contractor::class => 'Contractor'] as $class => $label) {
            foreach ($class::query()->where('is_active', true)->orderBy('name')->get() as $p) {
                $parties[$p->getMorphClass().':'.$p->id] = $label.' · '.$p->name;
            }
        }
        $debits = Decimal::sum(array_map(fn ($l) => is_numeric($l['debit']) ? (string) $l['debit'] : '0', $this->lines));
        $credits = Decimal::sum(array_map(fn ($l) => is_numeric($l['credit']) ? (string) $l['credit'] : '0', $this->lines));

        return view('finance::livewire.vouchers.form', [
            'accounts' => $accounts, 'parties' => $parties, 'debits' => $debits, 'credits' => $credits,
            'currencies' => Currency::query()->where('is_active', true)->pluck('code', 'code'),
        ])->title($this->voucher ? 'Edit '.$this->voucher->number : 'New journal voucher');
    }
}
