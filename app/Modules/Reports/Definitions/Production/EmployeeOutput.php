<?php

namespace App\Modules\Reports\Definitions\Production;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class EmployeeOutput extends Report
{
    public function key(): string
    {
        return 'employee-output';
    }

    public function title(): string
    {
        return 'Employee output';
    }

    public function group(): string
    {
        return 'HR';
    }

    public function description(): string
    {
        return 'Pieces accepted and rejected per wage employee, and wage amount.';
    }

    public function permissions(): array
    {
        return ['wage_work.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('employee', 'Employee'), new Column('quantity', 'Accepted', 'qty', total: true), new Column('rejected', 'Rejected', 'qty', total: true),
            new Column('amount', 'Wage amount', 'money', total: true), new Column('pending', 'Pending review', 'number'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $canSee = auth()->user()?->can('salary.view');

        return DB::table('wage_work as w')->join('employees as e', 'e.id', '=', 'w.employee_id')->where('w.status', '!=', 'rejected')
            ->whereBetween('w.work_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('e.name')->orderBy('e.name')
            ->get(['e.name as employee', DB::raw('SUM(w.quantity) as quantity'), DB::raw('SUM(w.rejected_qty) as rejected'), DB::raw('SUM(w.amount) as amount'),
                DB::raw("SUM(CASE WHEN w.status = 'pending' THEN 1 ELSE 0 END) as pending")])
            ->map(fn ($r) => array_merge((array) $r, $canSee ? [] : ['amount' => null]));
    }
}
