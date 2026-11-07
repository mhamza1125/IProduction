<?php

namespace App\Modules\Finance\Livewire\Receipts;

use App\Modules\Finance\Models\Receipt;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return Receipt::query()->with(['customer', 'moneyAccount']);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhere('reference', 'like', $like)->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number, customer, reference…';
    }

    protected function dateColumn(): string
    {
        return 'receipt_date';
    }

    protected function permission(): string
    {
        return 'receipts';
    }

    protected function title(): string
    {
        return 'Customer receipts';
    }

    protected function description(): string
    {
        return 'Money received from customers, in their currency. Amount cleared = deductions + net received.';
    }

    protected function showRoute(): string
    {
        return 'finance.receipts.show';
    }

    protected function createRoute(): ?string
    {
        return 'finance.receipts.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(Receipt): string> */
    protected function columns(): array
    {
        return [
            'Customer' => fn (Receipt $r) => $r->customer->name,
            'Account' => fn (Receipt $r) => $r->moneyAccount->name,
            'Cleared' => fn (Receipt $r) => Format::money($r->amount, $r->currency_code),
            'Net received' => fn (Receipt $r) => Format::money($r->net_received, $r->currency_code),
            'Unallocated' => fn (Receipt $r) => $r->isPosted() ? Format::money($r->unallocated(), $r->currency_code) : '',
        ];
    }

    protected function rightAligned(): array
    {
        return ['Cleared', 'Net received', 'Unallocated'];
    }
}
