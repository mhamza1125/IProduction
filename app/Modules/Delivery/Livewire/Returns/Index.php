<?php

namespace App\Modules\Delivery\Livewire\Returns;

use App\Modules\Delivery\Models\DeliveryReturn;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return DeliveryReturn::query()->with(['customer', 'deliveryLine.variant']);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like));
    }

    protected function dateColumn(): string
    {
        return 'return_date';
    }

    protected function permission(): string
    {
        return 'delivery_returns';
    }

    protected function title(): string
    {
        return 'Delivery returns';
    }

    protected function description(): string
    {
        return 'Goods returned by customers come back in Returned status for inspection.';
    }

    protected function showRoute(): string
    {
        return 'delivery.returns.show';
    }

    protected function createRoute(): ?string
    {
        return 'delivery.returns.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(DeliveryReturn): string> */
    protected function columns(): array
    {
        return [
            'Customer' => fn (DeliveryReturn $r) => $r->customer->name,
            'Item' => fn (DeliveryReturn $r) => $r->deliveryLine->variant->sku,
            'Quantity' => fn (DeliveryReturn $r) => Format::qty($r->quantity),
            'Reason' => fn (DeliveryReturn $r) => $r->reason,
        ];
    }

    protected function rightAligned(): array
    {
        return ['Quantity'];
    }
}
