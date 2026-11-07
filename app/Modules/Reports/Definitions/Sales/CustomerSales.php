<?php

namespace App\Modules\Reports\Definitions\Sales;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class CustomerSales extends Report
{
    public function key(): string
    {
        return 'customer-sales';
    }

    public function title(): string
    {
        return 'Customer sales';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Posted invoices by customer, in each customer\'s currency.';
    }

    public function permissions(): array
    {
        return ['invoices.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('customer', 'Customer'), new Column('invoices', 'Invoices', 'number'), new Column('net', 'Net of discount', 'money', 'currency', total: true),
            new Column('tax', 'Tax', 'money', 'currency', total: true), new Column('total', 'Total', 'money', 'currency', total: true), new Column('currency', 'Currency'),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')->whereNotNull('i.posted_at')
            ->whereBetween('i.invoice_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('c.name', 'i.currency_code')->orderBy('c.name')
            ->get(['c.name as customer', DB::raw('COUNT(*) as invoices'), DB::raw('SUM(i.subtotal - i.discount_total) as net'), DB::raw('SUM(i.tax_total) as tax'),
                DB::raw('SUM(i.total) as total'), 'i.currency_code as currency'])
            ->map(fn ($r) => (array) $r);
    }
}
