<?php

namespace App\Modules\Finance\Livewire\Transfers;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Transfer;
use App\Modules\Finance\Services\MoneyMovements;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Transfer $transfer = null;

    public string $transfer_date = '';

    public ?int $from_account_id = null;

    public ?int $to_account_id = null;

    public string $amount = '';

    public string $reference = '';

    public string $notes = '';

    public function mount(?Transfer $transfer = null): void
    {
        if ($transfer?->exists) {
            abort_unless($transfer->isDraft(), 403, 'Only drafts can be edited.');
            $this->transfer = $transfer;
            $this->fill($transfer->only(['from_account_id', 'to_account_id']));
            $this->amount = (string) $transfer->amount;
            $this->reference = (string) $transfer->reference;
            $this->notes = (string) $transfer->notes;
            $this->transfer_date = $transfer->transfer_date->format('Y-m-d');

            return;
        }
        $this->transfer_date = now()->format('Y-m-d');
    }

    public function save(MoneyMovements $moves): void
    {
        $this->authorize($this->transfer ? 'transfers.edit' : 'transfers.create');
        $data = $this->validate([
            'transfer_date' => ['required', 'date'], 'from_account_id' => ['required', 'exists:money_accounts,id'],
            'to_account_id' => ['required', 'exists:money_accounts,id', 'different:from_account_id'], 'amount' => ['required', 'numeric', 'gt:0'],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string'],
        ]);
        try {
            $doc = $moves->saveTransfer($this->transfer, $data);
        } catch (DocumentException $e) {
            $this->addError('to_account_id', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$doc->number} saved.");
        $this->redirectRoute('finance.transfers.show', $doc, navigate: true);
    }

    public function render(): View
    {
        return view('finance::livewire.transfers.form', [
            'accounts' => MoneyAccount::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($a) => [$a->id => $a->name.' ('.$a->currency_code.')']),
        ])->title($this->transfer ? 'Edit '.$this->transfer->number : 'New transfer');
    }
}
