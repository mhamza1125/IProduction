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
            ->groupBy('a.code', 'a.name', 'a.type', 'a.term')->orderBy('a.code')
            ->get(['a.code', 'a.name', 'a.type', 'a.term', DB::raw('SUM(l.debit) - SUM(l.credit) as net')]);
        $total = fn ($rows, $sign) => Decimal::mul(Decimal::sum($rows->pluck('net')->map(fn ($v) => (string) $v)), $sign);
        $isAsset = fn ($r) => in_array($r->type, ['asset', 'clearing'], true);
        $profit = $total($lines->whereIn('type', ['income', 'cost', 'expense']), '-1');

        // Assets and liabilities in current and non-current groups, each with a subtotal.
        $groups = [
            ['Assets', 'Current assets', $lines->filter(fn ($r) => $isAsset($r) && $r->term !== 'non_current'), '1'],
            ['Assets', 'Non-current assets', $lines->filter(fn ($r) => $isAsset($r) && $r->term === 'non_current'), '1'],
            ['Liabilities', 'Current liabilities', $lines->filter(fn ($r) => $r->type === 'liability' && $r->term !== 'non_current'), '-1'],
            ['Liabilities', 'Non-current liabilities', $lines->filter(fn ($r) => $r->type === 'liability' && $r->term === 'non_current'), '-1'],
        ];
        $rows = collect();
        foreach (['Assets', 'Liabilities'] as $section) {
            foreach (array_filter($groups, fn ($g) => $g[0] === $section) as [, $label, $accounts, $sign]) {
                $shown = $accounts->filter(fn ($r) => ! Decimal::isZero((string) $r->net));
                if ($shown->isEmpty()) {
                    continue;
                }
                foreach ($shown as $r) {
                    $rows->push(['section' => $label, 'code' => $r->code, 'account' => $r->name, 'amount' => Decimal::mul((string) $r->net, $sign)]);
                }
                $rows->push(['section' => $label, 'code' => '', 'account' => 'Total '.strtolower($label), 'amount' => $total($accounts, $sign)]);
            }
            $all = $section === 'Assets' ? $lines->filter($isAsset) : $lines->where('type', 'liability');
            $rows->push(['section' => $section, 'code' => '', 'account' => 'Total '.strtolower($section), 'amount' => $total($all, $section === 'Assets' ? '1' : '-1')]);
        }

        foreach ($lines->where('type', 'equity') as $r) {
            if (! Decimal::isZero((string) $r->net)) {
                $rows->push(['section' => 'Equity', 'code' => $r->code, 'account' => $r->name, 'amount' => Decimal::mul((string) $r->net, '-1')]);
            }
        }
        $rows->push(['section' => 'Equity', 'code' => '', 'account' => 'Profit not yet closed', 'amount' => $profit]);
        $rows->push(['section' => 'Equity', 'code' => '', 'account' => 'Total equity', 'amount' => Decimal::add($total($lines->where('type', 'equity'), '-1'), $profit)]);

        return $rows;
    }
}
