<?php

namespace App\Modules\Payroll\Livewire\Loans;

use App\Modules\Payroll\Models\EmployeeLoan;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return EmployeeLoan::query()->with('employee');
    }

    protected function searchUsing(Builder $query, string $like): void
    {
        $query->orWhereHas('employee', fn ($e) => $e->where('name', 'like', $like)->orWhere('code', 'like', $like));
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number, employee…';
    }

    protected function dateColumn(): string
    {
        return 'loan_date';
    }

    protected function permission(): string
    {
        return 'loans';
    }

    protected function title(): string
    {
        return 'Employee loans';
    }

    protected function description(): string
    {
        return 'Money lent to employees, recovered by monthly installments through payroll until the balance reaches zero.';
    }

    protected function showRoute(): string
    {
        return 'payroll.loans.show';
    }

    protected function createRoute(): ?string
    {
        return 'payroll.loans.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(EmployeeLoan): string> */
    protected function columns(): array
    {
        return [
            'Employee' => fn (EmployeeLoan $l) => $l->employee->name,
            'Amount' => fn (EmployeeLoan $l) => Format::money($l->amount),
            'Installment' => fn (EmployeeLoan $l) => Format::money($l->installment),
            'From' => fn (EmployeeLoan $l) => $l->start_month->format('M Y'),
            'Balance' => fn (EmployeeLoan $l) => $l->isPosted() ? Format::money($l->balance()) : '',
        ];
    }

    protected function rightAligned(): array
    {
        return ['Amount', 'Installment', 'Balance'];
    }
}
