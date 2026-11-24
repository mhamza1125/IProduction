<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cash flow statement, direct method, for one currency: every movement on the
 * cash and bank accounts in the period, classified by the other side of its
 * journal. When a journal has several other lines (a receipt with bank
 * charges), the cash is classified by the largest of them. Journals that only
 * move money between the company's own accounts are transfers and net to zero.
 * Closing cash = opening cash + net change, and agrees with the accounts.
 */
class CashFlow extends Report
{
    /** system key => [section, label for money in, label for money out] */
    private const BY_KEY = [
        'accounts_receivable' => ['Operating', 'Receipts from customers', 'Refunds to customers'],
        'customer_advances' => ['Operating', 'Receipts from customers', 'Refunds to customers'],
        'accounts_payable' => ['Operating', 'Refunds from vendors', 'Payments to vendors'],
        'vendor_advances' => ['Operating', 'Refunds from vendors', 'Payments to vendors'],
        'contractor_payable' => ['Operating', 'Refunds from contractors', 'Payments to contractors'],
        'contractor_advances' => ['Operating', 'Refunds from contractors', 'Payments to contractors'],
        'wages_payable' => ['Operating', 'Recovered from employees', 'Wages and salaries paid'],
        'employee_advances' => ['Operating', 'Recovered from employees', 'Advances and loans to employees'],
        'employee_loans' => ['Operating', 'Recovered from employees', 'Advances and loans to employees'],
        'taxes_payable' => ['Operating', 'Tax refunds', 'Taxes paid'],
        'currency_exchange_clearing' => ['Currency conversion', 'Received from conversions', 'Converted to another currency'],
        'foreign_receipts_realized' => ['Currency conversion', 'Received from conversions', 'Converted to another currency'],
    ];

    public function key(): string
    {
        return 'cash-flow';
    }

    public function title(): string
    {
        return 'Cash flow statement';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Cash in and out of all cash and bank accounts for a period, by operating, investing and financing activity (direct method), per currency.';
    }

    public function permissions(): array
    {
        return ['bank_balance.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange(), new Filter('currency', 'Currency', options: DB::table('currencies')->orderBy('code')->pluck('code', 'code')->all(), default: 'PKR')];
    }

    public function columns(): array
    {
        return [new Column('section', 'Section'), new Column('line', 'Item'), new Column('amount', 'Amount', 'money', 'currency'), new Column('currency', 'Currency')];
    }

    protected function build(array $filters): iterable
    {
        $currency = ($filters['currency'] ?? '') ?: 'PKR';
        $from = $this->from($filters)?->toDateString();
        $to = $this->to($filters)?->toDateString();

        $opening = (string) DB::table('journal_lines')->whereNotNull('money_account_id')->where('currency_code', $currency)
            ->when($from, fn ($q) => $q->where('line_date', '<', $from))->sum(DB::raw('debit - credit'));

        // Net cash per journal in the period.
        $cash = DB::table('journal_lines')->whereNotNull('money_account_id')->where('currency_code', $currency)
            ->when($from, fn ($q) => $q->where('line_date', '>=', $from))->when($to, fn ($q) => $q->where('line_date', '<=', $to))
            ->groupBy('journal_id')->get(['journal_id', DB::raw('SUM(debit) - SUM(credit) as net')])
            ->filter(fn ($r) => ! Decimal::isZero((string) $r->net));

        // The other side of those journals, largest line first.
        $counterparts = $cash->isEmpty() ? collect() : DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')
            ->whereIn('l.journal_id', $cash->pluck('journal_id'))->whereNull('l.money_account_id')
            ->get(['l.journal_id', 'a.system_key', 'a.type', 'a.term', DB::raw('ABS(l.debit - l.credit) as size')])
            ->sortByDesc(fn ($r) => (float) $r->size)->groupBy('journal_id');

        $totals = [];
        foreach ($cash as $row) {
            $in = Decimal::compare((string) $row->net, '0') > 0;
            [$section, $label] = $this->classify($counterparts->get($row->journal_id)?->first(), $in);
            $totals[$section][$label] = Decimal::add($totals[$section][$label] ?? '0', (string) $row->net);
        }

        $rows = collect();
        $net = '0';
        foreach (['Operating', 'Investing', 'Financing', 'Currency conversion', 'Transfers'] as $section) {
            if (empty($totals[$section])) {
                continue;
            }
            ksort($totals[$section]);
            foreach ($totals[$section] as $label => $amount) {
                $rows->push(['section' => $section, 'line' => $label, 'amount' => $amount, 'currency' => $currency]);
            }
            $subtotal = Decimal::sum(array_values($totals[$section]));
            $net = Decimal::add($net, $subtotal);
            $rows->push(['section' => $section, 'line' => 'Net cash from '.strtolower($section).($section === 'Transfers' ? ' between own accounts' : ' activities'), 'amount' => $subtotal, 'currency' => $currency]);
        }

        return $rows->merge(new Collection([
            ['section' => 'Summary', 'line' => 'Net change in cash', 'amount' => $net, 'currency' => $currency],
            ['section' => 'Summary', 'line' => 'Cash at the start', 'amount' => Decimal::round($opening), 'currency' => $currency],
            ['section' => 'Summary', 'line' => 'Cash at the end', 'amount' => Decimal::add(Decimal::round($opening), $net), 'currency' => $currency],
        ]));
    }

    /** @return array{0: string, 1: string} section and line label */
    private function classify(?object $counterpart, bool $in): array
    {
        if ($counterpart === null) {
            return ['Transfers', 'Transfers between cash and bank accounts'];
        }
        if ($counterpart->system_key !== null && isset(self::BY_KEY[$counterpart->system_key])) {
            [$section, $inLabel, $outLabel] = self::BY_KEY[$counterpart->system_key];

            return [$section, $in ? $inLabel : $outLabel];
        }

        return match (true) {
            $counterpart->type === 'asset' && $counterpart->term === 'non_current' => ['Investing', $in ? 'Sale of fixed assets' : 'Purchase of fixed assets'],
            $counterpart->type === 'equity' => ['Financing', $in ? 'Capital introduced' : 'Owner drawings'],
            $counterpart->type === 'liability' && $counterpart->term === 'non_current' => ['Financing', $in ? 'Long-term borrowing' : 'Repayment of long-term borrowing'],
            in_array($counterpart->type, ['expense', 'cost'], true) => ['Operating', $in ? 'Expense refunds' : 'Expenses paid'],
            $counterpart->type === 'income' => ['Operating', $in ? 'Other income received' : 'Other income refunded'],
            default => ['Operating', $in ? 'Other receipts' : 'Other payments'],
        };
    }
}
