<?php

namespace App\Modules\Purchasing\Livewire\LandedCosts;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Services\LandedCosts;
use App\Support\Documents\Document;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public LandedCost $landedCost;

    public function mount(LandedCost $landedCost): void
    {
        $this->landedCost = $landedCost->load(['vendor', 'moneyAccount']);
    }

    protected function model(): Model&Document
    {
        return $this->landedCost;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(LandedCosts::class)->post($this->landedCost, $overrideReason);
    }

    protected function heading(): string
    {
        return 'Landed cost '.$this->landedCost->number;
    }

    protected function details(): array
    {
        $d = $this->landedCost;

        return [
            'Date' => Format::date($d->cost_date), 'Total' => Format::money($d->total, 'PKR'), 'Spread' => LandedCost::METHODS[$d->method] ?? $d->method,
            'Paid from' => $d->moneyAccount?->name, 'Payable to' => $d->vendor?->name,
            'Paid so far' => $d->isPayable() && $d->isPosted() ? Format::money($d->paid_amount, 'PKR') : null,
            'Reference' => $d->reference, 'Notes' => $d->notes,
        ];
    }

    protected function body(): ?string
    {
        return 'purchasing::livewire.landed-costs.body';
    }

    protected function bodyData(): array
    {
        return [
            'charges' => $this->landedCost->charges()->get(),
            'receipts' => $this->landedCost->receipts()->with('order.vendor')->get(),
            'allocations' => $this->landedCost->allocations()->with(['receiptLine.variant.item', 'receiptLine.receipt', 'lot'])->get(),
            'types' => LandedCost::CHARGE_TYPES,
        ];
    }

    protected function editRoute(): string
    {
        return 'purchasing.landed-costs.edit';
    }

    protected function indexRoute(): string
    {
        return 'purchasing.landed-costs.index';
    }
}
