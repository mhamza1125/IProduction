<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class MoneyBalances extends Report
{
    public function key(): string
    {
        return 'money-balances';
    }

    public function title(): string
    {
        return 'Cash, bank and foreign balances';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Balance of every cash and bank account in its own currency.';
    }

    public function permissions(): array
    {
        return ['accounts.view', 'bank_balance.view'];
    }

    public function filters(): array
    {
        return [new Filter('to', 'As of', 'date', default: now()->toDateString())];
    }

    public function columns(): array
    {
        return [
            new Column('account', 'Account'), new Column('type', 'Type'), new Column('bank', 'Bank'), new Column('balance', 'Balance', 'money', 'currency', total: true), new Column('currency', 'Currency'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $as = $this->to($filters)?->toDateString() ?? now()->toDateString();
        $sums = DB::table('journal_lines')->whereNotNull('money_account_id')->where('line_date', '<=', $as)->groupBy('money_account_id')
            ->pluck(DB::raw('SUM(debit) - SUM(credit)'), 'money_account_id');

        return DB::table('money_accounts')->where('is_active', true)->orderBy('currency_code')->orderBy('name')
            ->get(['id', 'name', 'type', 'bank_name', 'currency_code'])
            ->map(fn ($a) => ['account' => $a->name, 'type' => ucfirst($a->type), 'bank' => $a->bank_name, 'balance' => Decimal::round((string) ($sums[$a->id] ?? '0')), 'currency' => $a->currency_code]);
    }
}
