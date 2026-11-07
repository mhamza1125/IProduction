<?php

namespace App\Modules\Finance\Livewire\Vouchers;

use App\Modules\Finance\Models\JournalVoucher;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return JournalVoucher::query()->with([]);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhere('description', 'like', $like);
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number, description…';
    }

    protected function dateColumn(): string
    {
        return 'voucher_date';
    }

    protected function permission(): string
    {
        return 'journal_vouchers';
    }

    protected function title(): string
    {
        return 'Journal vouchers';
    }

    protected function description(): string
    {
        return 'Manual balanced debit and credit entries for adjustments and charges.';
    }

    protected function showRoute(): string
    {
        return 'finance.vouchers.show';
    }

    protected function createRoute(): ?string
    {
        return 'finance.vouchers.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(JournalVoucher): string> */
    protected function columns(): array
    {
        return [
            'Description' => fn (JournalVoucher $v) => $v->description,
            'Total' => fn (JournalVoucher $v) => Format::money($v->total, $v->currency_code),
        ];
    }

    protected function rightAligned(): array
    {
        return ['Total'];
    }
}
