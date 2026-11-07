<?php

namespace App\Modules\Payroll\Livewire\Advances;

use App\Modules\Payroll\Models\SalaryAdvance;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return SalaryAdvance::query()->with('employee');
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
        return 'advance_date';
    }

    protected function permission(): string
    {
        return 'advances';
    }

    protected function title(): string
    {
        return 'Salary advances';
    }

    protected function description(): string
    {
        return 'Part of a salary paid early. Payroll deducts it in its salary month; any unused part carries forward.';
    }

    protected function showRoute(): string
    {
        return 'payroll.advances.show';
    }

    protected function createRoute(): ?string
    {
        return 'payroll.advances.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(SalaryAdvance): string> */
    protected function columns(): array
    {
        return [
            'Employee' => fn (SalaryAdvance $a) => $a->employee->name,
            'Salary month' => fn (SalaryAdvance $a) => $a->salary_month->format('M Y'),
            'Amount' => fn (SalaryAdvance $a) => Format::money($a->amount),
            'Balance' => fn (SalaryAdvance $a) => $a->isPosted() ? Format::money($a->balance()) : '',
        ];
    }

    protected function rightAligned(): array
    {
        return ['Amount', 'Amount', 'Balance'];
    }
}
