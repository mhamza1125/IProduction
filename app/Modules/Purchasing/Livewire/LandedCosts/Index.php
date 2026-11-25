<?php

namespace App\Modules\Purchasing\Livewire\LandedCosts;

use App\Modules\Purchasing\Models\LandedCost;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return LandedCost::query()->with(['vendor', 'moneyAccount', 'receipts']);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhere('reference', 'like', $like)->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like))
            ->orWhereHas('receipts', fn ($r) => $r->where('number', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number, vendor, receipt, reference…';
    }

    protected function dateColumn(): string
    {
        return 'cost_date';
    }

    protected function permission(): string
    {
        return 'landed_costs';
    }

    protected function title(): string
    {
        return 'Landed costs';
    }

    protected function description(): string
    {
        return 'Freight, delivery, customs duty and other purchase charges added to the cost of the goods they were paid for, instead of being expensed.';
    }

    protected function showRoute(): string
    {
        return 'purchasing.landed-costs.show';
    }

    protected function createRoute(): ?string
    {
        return 'purchasing.landed-costs.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(LandedCost): string> */
    protected function columns(): array
    {
        return [
            'Receipts' => fn (LandedCost $d) => $d->receipts->pluck('number')->implode(', '),
            'Paid from / payable to' => fn (LandedCost $d) => $d->moneyAccount->name ?? 'Payable: '.$d->vendor?->name,
            'Amount' => fn (LandedCost $d) => Format::money($d->total, 'PKR'),
        ];
    }

    protected function rightAligned(): array
    {
        return ['Amount'];
    }
}
