<?php

namespace App\Modules\Finance\Livewire\Conversions;

use App\Modules\Finance\Models\CurrencyConversion;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return CurrencyConversion::query()->with(['fromAccount', 'toAccount']);
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
        return 'conversion_date';
    }

    protected function permission(): string
    {
        return 'currency_conversions';
    }

    protected function title(): string
    {
        return 'Currency conversions';
    }

    protected function description(): string
    {
        return 'Foreign money converted into PKR. The PKR received is typed in; no exchange rate is needed.';
    }

    protected function showRoute(): string
    {
        return 'finance.conversions.show';
    }

    protected function createRoute(): ?string
    {
        return 'finance.conversions.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(CurrencyConversion): string> */
    protected function columns(): array
    {
        return [
            'From' => fn (CurrencyConversion $c) => $c->fromAccount->name,
            'Amount' => fn (CurrencyConversion $c) => Format::money($c->from_amount, $c->fromAccount->currency_code),
            'To' => fn (CurrencyConversion $c) => $c->toAccount->name,
            'PKR received' => fn (CurrencyConversion $c) => Format::money($c->pkr_received, 'PKR'),
            'Effective rate' => fn (CurrencyConversion $c) => (string) $c->effectiveRate(),
        ];
    }

    protected function rightAligned(): array
    {
        return ['Amount', 'PKR received', 'Effective rate'];
    }
}
