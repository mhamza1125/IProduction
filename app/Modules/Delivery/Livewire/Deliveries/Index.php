<?php

namespace App\Modules\Delivery\Livewire\Deliveries;

use App\Modules\Delivery\Livewire\DocumentList;
use App\Modules\Delivery\Models\Delivery;
use App\Support\Decimal;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return Delivery::query()->with(['customer', 'lines']);
    }

    protected function dateColumn(): string
    {
        return 'delivery_date';
    }

    protected function permission(): string
    {
        return 'deliveries';
    }

    protected function title(): string
    {
        return 'Deliveries';
    }

    protected function description(): string
    {
        return 'Dispatches to customers. One delivery can combine several orders of one customer and currency.';
    }

    protected function showRoute(): string
    {
        return 'delivery.deliveries.show';
    }

    protected function createRoute(): ?string
    {
        return 'delivery.deliveries.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'allocated', 'packed', 'dispatched', 'cancelled'];
    }

    /** @return array<string, callable(Delivery): string> */
    protected function columns(): array
    {
        return [
            'Customer' => fn (Delivery $d) => $d->customer->name,
            'Lines' => fn (Delivery $d) => (string) $d->lines->count(),
            'Pieces' => fn (Delivery $d) => Format::qty(Decimal::sum($d->lines->pluck('quantity'), 4)),
            'Tracking' => fn (Delivery $d) => (string) $d->tracking_number,
        ];
    }

    protected function rightAligned(): array
    {
        return ['Lines', 'Pieces'];
    }
}
