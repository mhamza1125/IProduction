<?php

namespace App\Modules\Finance\Livewire\Transfers;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Finance\Models\Transfer;
use App\Modules\Finance\Services\MoneyMovements;
use App\Support\Documents\Document;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public Transfer $transfer;

    public function mount(Transfer $transfer): void
    {
        $this->transfer = $transfer->load(['fromAccount', 'toAccount']);
    }

    protected function model(): Model&Document
    {
        return $this->transfer;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(MoneyMovements::class)->postTransfer($this->transfer, $overrideReason);
    }

    protected function heading(): string
    {
        return 'Transfer '.$this->transfer->number;
    }

    protected function details(): array
    {
        $t = $this->transfer;

        return ['Date' => Format::date($t->transfer_date), 'From' => $t->fromAccount->name, 'To' => $t->toAccount->name,
            'Amount' => Format::money($t->amount, $t->fromAccount->currency_code), 'Reference' => $t->reference, 'Notes' => $t->notes];
    }

    protected function editRoute(): string
    {
        return 'finance.transfers.edit';
    }

    protected function indexRoute(): string
    {
        return 'finance.transfers.index';
    }
}
