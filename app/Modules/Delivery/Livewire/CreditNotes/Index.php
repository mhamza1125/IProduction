<?php

namespace App\Modules\Delivery\Livewire\CreditNotes;

use App\Modules\Delivery\Models\CreditNote;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return CreditNote::query()->with(['customer', 'invoice']);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like));
    }

    protected function dateColumn(): string
    {
        return 'credit_date';
    }

    protected function permission(): string
    {
        return 'credit_notes';
    }

    protected function title(): string
    {
        return 'Credit notes';
    }

    protected function description(): string
    {
        return "Credit notes reduce what a customer owes, in the customer's currency.";
    }

    protected function showRoute(): string
    {
        return 'delivery.credit-notes.show';
    }

    protected function createRoute(): ?string
    {
        return 'delivery.credit-notes.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(CreditNote): string> */
    protected function columns(): array
    {
        return [
            'Customer' => fn (CreditNote $c) => $c->customer->name,
            'Invoice' => fn (CreditNote $c) => (string) $c->invoice?->number,
            'Reason' => fn (CreditNote $c) => $c->reason,
            'Total' => fn (CreditNote $c) => Format::money($c->total, $c->currency_code),
        ];
    }

    protected function rightAligned(): array
    {
        return ['Total'];
    }
}
