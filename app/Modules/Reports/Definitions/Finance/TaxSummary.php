<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Sales tax for a period: output tax on posted invoices by rate and currency,
 * untaxed (zero-rated or exempt) sales, and input tax on posted vendor bills.
 * Credit notes are shown as one line per currency (not split by rate).
 */
class TaxSummary extends Report
{
    public function key(): string
    {
        return 'tax-summary';
    }

    public function title(): string
    {
        return 'Sales tax summary';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Output tax by rate on invoices, untaxed sales, and input tax on vendor bills, for a period.';
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
            new Column('section', 'Section'), new Column('rate', 'Rate'), new Column('documents', 'Documents', 'number'),
            new Column('taxable', 'Value', 'money', 'currency'), new Column('tax', 'Tax', 'money', 'currency'), new Column('currency', 'Currency'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $range = [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()];

        $sales = DB::table('invoice_lines as l')->join('invoices as i', 'i.id', '=', 'l.invoice_id')
            ->whereNotNull('i.posted_at')->whereNull('i.cancelled_at')->whereBetween('i.invoice_date', $range)
            ->groupBy('i.currency_code', 'l.tax_rate')->orderBy('i.currency_code')->orderBy('l.tax_rate')
            ->get(['i.currency_code', 'l.tax_rate', DB::raw('COUNT(DISTINCT i.id) as documents'), DB::raw('SUM(l.amount) as taxable'), DB::raw('SUM(l.tax_amount) as tax')]);

        $rows = [];
        foreach ($sales as $r) {
            $rows[] = [
                'section' => $r->tax_rate === null ? 'Sales without tax (zero-rated / exempt / export)' : 'Output tax on sales',
                'rate' => $r->tax_rate === null ? '—' : Decimal::round((string) $r->tax_rate, 2).'%',
                'documents' => (int) $r->documents, 'taxable' => (string) $r->taxable, 'tax' => (string) $r->tax, 'currency' => $r->currency_code,
            ];
        }

        $credits = DB::table('credit_notes')->whereNotNull('posted_at')->whereNull('cancelled_at')->whereBetween('credit_date', $range)
            ->groupBy('currency_code')->get(['currency_code', DB::raw('COUNT(*) as documents'), DB::raw('SUM(subtotal) as subtotal'), DB::raw('SUM(tax_total) as tax')]);
        foreach ($credits as $c) {
            $rows[] = ['section' => 'Credit notes (reduce sales and output tax)', 'rate' => '—', 'documents' => (int) $c->documents,
                'taxable' => Decimal::mul((string) $c->subtotal, '-1'), 'tax' => Decimal::mul((string) $c->tax, '-1'), 'currency' => $c->currency_code];
        }

        $bills = DB::table('vendor_bills')->whereNotNull('posted_at')->whereNull('cancelled_at')->whereBetween('bill_date', $range)
            ->first([DB::raw('COUNT(*) as documents'), DB::raw('SUM(goods_amount) as goods'), DB::raw('SUM(tax_amount) as tax')]);
        if ($bills !== null && (int) $bills->documents > 0) {
            $rows[] = ['section' => 'Input tax on vendor bills', 'rate' => '—', 'documents' => (int) $bills->documents, 'taxable' => (string) $bills->goods, 'tax' => (string) $bills->tax, 'currency' => 'PKR'];
        }

        return $rows;
    }
}
