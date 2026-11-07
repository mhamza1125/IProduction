<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class TrialBalance extends Report
{
    public function key(): string
    {
        return 'trial-balance';
    }

    public function title(): string
    {
        return 'Trial balance';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Debit and credit balance of every account, in one currency.';
    }

    public function permissions(): array
    {
        return ['ledgers.view'];
    }

    public function filters(): array
    {
        return [new Filter('to', 'As of', 'date', default: now()->toDateString()), new Filter('currency', 'Currency', options: DB::table('currencies')->where('is_active', true)->pluck('code', 'code')->all(), default: 'PKR')];
    }

    public function columns(): array
    {
        return [
            new Column('code', 'Code'), new Column('account', 'Account'), new Column('type', 'Type'), new Column('debit', 'Debit', 'money', total: true), new Column('credit', 'Credit', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->where('l.currency_code', $filters['currency'] ?: 'PKR')->where('l.line_date', '<=', $this->to($filters)?->toDateString() ?? now()->toDateString())
            ->groupBy('a.code', 'a.name', 'a.type')->orderBy('a.code')
            ->get(['a.code', 'a.name', 'a.type', DB::raw('SUM(l.debit) - SUM(l.credit) as net')])
            ->filter(fn ($r) => ! Decimal::isZero((string) $r->net))
            ->map(fn ($r) => ['code' => $r->code, 'account' => $r->name, 'type' => ucfirst($r->type),
                'debit' => Decimal::compare((string) $r->net, '0') > 0 ? Decimal::round((string) $r->net) : null,
                'credit' => Decimal::isNegative((string) $r->net) ? Decimal::sub('0', (string) $r->net) : null])->values();
    }

    public function note(): ?string
    {
        return 'Each currency is a separate trial balance: foreign documents stay in their currency and only conversions create PKR.';
    }
}
