<?php

namespace App\Modules\Finance\Livewire\Transfers;

use App\Modules\Finance\Models\Transfer;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return Transfer::query()->with(['fromAccount', 'toAccount']);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhere('reference', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number, reference…';
    }

    protected function dateColumn(): string
    {
        return 'transfer_date';
    }

    protected function permission(): string
    {
        return 'transfers';
    }

    protected function title(): string
    {
        return 'Transfers';
    }

    protected function description(): string
    {
        return 'Money moved between company accounts of the same currency.';
    }

    protected function showRoute(): string
    {
        return 'finance.transfers.show';
    }

    protected function createRoute(): ?string
    {
        return 'finance.transfers.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(Transfer): string> */
    protected function columns(): array
    {
        return [
            'From' => fn (Transfer $t) => $t->fromAccount->name,
            'To' => fn (Transfer $t) => $t->toAccount->name,
            'Amount' => fn (Transfer $t) => Format::money($t->amount, $t->fromAccount->currency_code),
        ];
    }

    protected function rightAligned(): array
    {
        return ['Amount'];
    }
}
