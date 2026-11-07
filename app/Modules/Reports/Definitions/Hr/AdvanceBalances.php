<?php

namespace App\Modules\Reports\Definitions\Hr;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdvanceBalances extends Report
{
    public function key(): string
    {
        return 'advance-balances';
    }

    public function title(): string
    {
        return 'Salary advances outstanding';
    }

    public function group(): string
    {
        return 'HR';
    }

    public function description(): string
    {
        return 'Advances not yet recovered by payroll.';
    }

    public function permissions(): array
    {
        return ['advances.view', 'salary.view'];
    }

    public function filters(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            new Column('advance', 'Advance', linkKey: 'url'), new Column('employee', 'Employee'), new Column('date', 'Date', 'date'), new Column('month', 'Salary month'),
            new Column('amount', 'Amount', 'money', total: true), new Column('balance', 'Not recovered', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('salary_advances as a')->join('employees as e', 'e.id', '=', 'a.employee_id')->whereNotNull('a.posted_at')->whereColumn('a.recovered_amount', '<', 'a.amount')->orderBy('a.salary_month')
            ->get(['a.id', 'a.number', 'e.name', 'a.advance_date', 'a.salary_month', 'a.amount', DB::raw('a.amount - a.recovered_amount as balance')])
            ->map(fn ($r) => ['advance' => $r->number, 'url' => route('payroll.advances.show', $r->id), 'employee' => $r->name, 'date' => $r->advance_date,
                'month' => Carbon::parse($r->salary_month)->format('M Y'), 'amount' => $r->amount, 'balance' => $r->balance]);
    }
}
