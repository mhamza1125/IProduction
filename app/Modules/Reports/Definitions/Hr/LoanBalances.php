<?php

namespace App\Modules\Reports\Definitions\Hr;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class LoanBalances extends Report
{
    public function key(): string
    {
        return 'loan-balances';
    }

    public function title(): string
    {
        return 'Loan balances';
    }

    public function group(): string
    {
        return 'HR';
    }

    public function description(): string
    {
        return 'Employee loans with installment and remaining balance.';
    }

    public function permissions(): array
    {
        return ['loans.view', 'salary.view'];
    }

    public function filters(): array
    {
        return [];
    }

    public function columns(): array
    {
        return [
            new Column('loan', 'Loan', linkKey: 'url'), new Column('employee', 'Employee'), new Column('date', 'Date', 'date'), new Column('amount', 'Amount', 'money', total: true),
            new Column('installment', 'Installment', 'money'), new Column('recovered', 'Recovered', 'money', total: true), new Column('balance', 'Balance', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('employee_loans as l')->join('employees as e', 'e.id', '=', 'l.employee_id')->whereNotNull('l.posted_at')->orderBy('e.name')
            ->get(['l.id', 'l.number', 'e.name', 'l.loan_date', 'l.amount', 'l.installment', 'l.recovered_amount', DB::raw('l.amount - l.recovered_amount as balance')])
            ->map(fn ($r) => ['loan' => $r->number, 'url' => route('payroll.loans.show', $r->id), 'employee' => $r->name, 'date' => $r->loan_date, 'amount' => $r->amount,
                'installment' => $r->installment, 'recovered' => $r->recovered_amount, 'balance' => $r->balance]);
    }
}
