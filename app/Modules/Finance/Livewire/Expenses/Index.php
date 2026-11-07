<?php

namespace App\Modules\Finance\Livewire\Expenses;

use App\Modules\Finance\Models\Expense;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return Expense::query()->with(['expenseAccount', 'moneyAccount', 'vendor']);
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhere('reference', 'like', $like)->orWhereHas('expenseAccount', fn ($a) => $a->where('name', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number, head, reference…';
    }

    protected function dateColumn(): string
    {
        return 'expense_date';
    }

    protected function permission(): string
    {
        return 'expenses';
    }

    protected function title(): string
    {
        return 'Expenses';
    }

    protected function description(): string
    {
        return 'Expenses paid now from cash or bank, or recorded as payable to a vendor.';
    }

    protected function showRoute(): string
    {
        return 'finance.expenses.show';
    }

    protected function createRoute(): ?string
    {
        return 'finance.expenses.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(Expense): string> */
    protected function columns(): array
    {
        return [
            'Head' => fn (Expense $e) => $e->expenseAccount->name,
            'Paid from / payable to' => fn (Expense $e) => $e->moneyAccount->name ?? 'Payable: '.$e->vendor?->name,
            'Amount' => fn (Expense $e) => Format::money($e->amount, 'PKR'),
        ];
    }

    protected function rightAligned(): array
    {
        return ['Amount'];
    }
}
