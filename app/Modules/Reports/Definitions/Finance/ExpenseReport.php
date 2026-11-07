<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class ExpenseReport extends Report
{
    public function key(): string
    {
        return 'expenses';
    }

    public function title(): string
    {
        return 'Expense report';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Expenses by head for a period (PKR).';
    }

    public function permissions(): array
    {
        return ['ledgers.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('code', 'Code'), new Column('head', 'Expense head'), new Column('amount', 'Amount (PKR)', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('a.type', 'expense')->where('l.currency_code', 'PKR')
            ->whereBetween('l.line_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('a.code', 'a.name')->orderBy('a.code')
            ->get(['a.code', 'a.name as head', DB::raw('SUM(l.debit) - SUM(l.credit) as amount')])->map(fn ($r) => (array) $r);
    }
}
