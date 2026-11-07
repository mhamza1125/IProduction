<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class ProfitAndLoss extends Report
{
    public function key(): string
    {
        return 'profit-and-loss';
    }

    public function title(): string
    {
        return 'Profit and loss';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Income, cost and expenses for a period, in one currency.';
    }

    public function permissions(): array
    {
        return ['ledgers.view', 'profit.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange(now()->startOfYear()->toDateString()), new Filter('currency', 'Currency', options: DB::table('currencies')->where('is_active', true)->pluck('code', 'code')->all(), default: 'PKR')];
    }

    public function columns(): array
    {
        return [
            new Column('section', 'Section'), new Column('code', 'Code'), new Column('account', 'Account'), new Column('amount', 'Amount', 'money'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $lines = DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->whereIn('a.type', ['income', 'cost', 'expense'])
            ->where('l.currency_code', $filters['currency'] ?: 'PKR')
            ->whereBetween('l.line_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('a.code', 'a.name', 'a.type')->orderBy('a.code')
            ->get(['a.code', 'a.name', 'a.type', DB::raw('SUM(l.credit) - SUM(l.debit) as net')]);
        $rows = collect();
        $totals = [];
        foreach (['income' => 'Income', 'cost' => 'Cost', 'expense' => 'Expenses'] as $type => $label) {
            $group = $lines->where('type', $type);
            $sign = $type === 'income' ? '1' : '-1';
            foreach ($group as $r) {
                $rows->push(['section' => $label, 'code' => $r->code, 'account' => $r->name, 'amount' => Decimal::mul((string) $r->net, $sign)]);
            }
            $totals[$type] = Decimal::mul(Decimal::sum($group->pluck('net')->map(fn ($v) => (string) $v)), $sign);
            $rows->push(['section' => $label, 'code' => '', 'account' => 'Total '.strtolower($label), 'amount' => $totals[$type]]);
        }
        $rows->push(['section' => 'Result', 'code' => '', 'account' => 'Net profit', 'amount' => Decimal::sub(Decimal::sub($totals['income'], $totals['cost']), $totals['expense'])]);

        return $rows;
    }

    public function note(): ?string
    {
        return 'PKR profit and loss includes foreign income only when it is converted (Foreign Receipts Realized). Choose a currency to see its own figures.';
    }
}
