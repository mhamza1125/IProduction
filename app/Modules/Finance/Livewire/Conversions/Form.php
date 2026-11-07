<?php

namespace App\Modules\Finance\Livewire\Conversions;

use App\Modules\Finance\Models\CurrencyConversion;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyMovements;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?CurrencyConversion $conversion = null;

    public string $conversion_date = '';

    public ?int $from_account_id = null;

    public string $from_amount = '';

    public ?int $to_account_id = null;

    public string $pkr_received = '';

    public string $deductions = '';

    public ?int $deduction_account_id = null;

    public string $reference = '';

    public string $notes = '';

    public function mount(?CurrencyConversion $conversion = null): void
    {
        if ($conversion?->exists) {
            abort_unless($conversion->isDraft(), 403, 'Only drafts can be edited.');
            $this->conversion = $conversion;
            $this->fill($conversion->only(['from_account_id', 'to_account_id', 'deduction_account_id']));
            foreach (['from_amount', 'pkr_received', 'deductions', 'reference', 'notes'] as $f) {
                $this->{$f} = (string) $conversion->{$f};
            }
            $this->conversion_date = $conversion->conversion_date->format('Y-m-d');

            return;
        }
        $this->conversion_date = now()->format('Y-m-d');
        $this->deduction_account_id = GlAccount::query()->where('system_key', 'bank_charges')->value('id');
    }

    public function save(MoneyMovements $moves): void
    {
        $this->authorize($this->conversion ? 'currency_conversions.edit' : 'currency_conversions.create');
        $data = $this->validate([
            'conversion_date' => ['required', 'date'], 'from_account_id' => ['required', 'exists:money_accounts,id'], 'from_amount' => ['required', 'numeric', 'gt:0'],
            'to_account_id' => ['required', 'exists:money_accounts,id'], 'pkr_received' => ['required', 'numeric', 'gt:0'], 'deductions' => ['nullable', 'numeric', 'min:0'],
            'deduction_account_id' => ['nullable', 'exists:gl_accounts,id'], 'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string'],
        ]);
        try {
            $doc = $moves->saveConversion($this->conversion, $data);
        } catch (DocumentException $e) {
            $this->addError('from_account_id', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$doc->number} saved.");
        $this->redirectRoute('finance.conversions.show', $doc, navigate: true);
    }

    public function render(Ledgers $ledgers): View
    {
        $from = $this->from_account_id ? MoneyAccount::query()->find($this->from_account_id) : null;
        $rate = is_numeric($this->from_amount) && (float) $this->from_amount > 0 && is_numeric($this->pkr_received)
            ? Decimal::div(Decimal::add((string) $this->pkr_received, is_numeric($this->deductions) ? (string) $this->deductions : '0'), (string) $this->from_amount, 4) : null;

        return view('finance::livewire.conversions.form', [
            'foreign' => MoneyAccount::query()->active()->where('currency_code', '!=', 'PKR')->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.' ('.$a->currency_code.')']),
            'pkr' => MoneyAccount::query()->active()->where('currency_code', 'PKR')->pluck('name', 'id'),
            'heads' => GlAccount::query()->active()->where('type', 'expense')->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' '.$a->name]),
            'balance' => $from && auth()->user()->can('bank_balance.view') ? $ledgers->moneyBalance($from) : null,
            'from' => $from,
            'rate' => $rate,
        ])->title($this->conversion ? 'Edit '.$this->conversion->number : 'New currency conversion');
    }
}
