<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class BalanceSheet extends Report
{
    public function key(): string
    {
        return 'balance-sheet';
    }

    public function title(): string
    {
        return 'Balance sheet';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Assets, liabilities and equity as of a date, in one currency.';
    }

    public function permissions(): array
    {
        return ['ledgers.view', 'profit.view'];
    }

    public function filters(): array
    {
        return [new Filter('to', 'As of', 'date', default: now()->toDateString()), new Filter('currency', 'Currency', options: DB::table('currencies')->where('is_active', true)->pluck('code', 'code')->all(), default: 'PKR')];
    }

    public function columns(): array
    {
        return [
            new Column('section', 'Section'), new Column('code', 'Code'), new Column('account', 'Account'), new Column('amount', 'Amount', 'money'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $lines = DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('l.currency_code', $filters['currency'] ?: 'PKR')->where('l.line_date', '<=', $this->to($filters)?->toDateString() ?? now()->toDateString())
            ->groupBy('a.code', 'a.name', 'a.type')->orderBy('a.code')
            ->get(['a.code', 'a.name', 'a.type', DB::raw('SUM(l.debit) - SUM(l.credit) as net')]);
        $rows = collect();
        $sum = fn ($types, $sign) => Decimal::mul(Decimal::sum($lines->whereIn('type', $types)->pluck('net')->map(fn ($v) => (string) $v)), $sign);
        foreach (['Assets' => [['asset', 'clearing'], '1'], 'Liabilities' => [['liability'], '-1'], 'Equity' => [['equity'], '-1']] as $label => [$types, $sign]) {
            foreach ($lines->whereIn('type', $types) as $r) {
                if (! Decimal::isZero((string) $r->net)) {
                    $rows->push(['section' => $label, 'code' => $r->code, 'account' => $r->name, 'amount' => Decimal::mul((string) $r->net, $sign)]);
                }
            }
            if ($label === 'Equity') {
                $rows->push(['section' => $label, 'code' => '', 'account' => 'Profit not yet closed', 'amount' => $sum(['income', 'cost', 'expense'], '-1')]);
            }
            $rows->push(['section' => $label, 'code' => '', 'account' => 'Total '.strtolower($label), 'amount' => Decimal::add($sum($types, $sign), $label === 'Equity' ? $sum(['income', 'cost', 'expense'], '-1') : '0')]);
        }

        return $rows;
    }
}
