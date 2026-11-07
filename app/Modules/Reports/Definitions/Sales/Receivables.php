<?php

namespace App\Modules\Reports\Definitions\Sales;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Receivables extends Report
{
    public function key(): string
    {
        return 'receivables';
    }

    public function title(): string
    {
        return 'Receivables and invoice status';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Posted invoices still owed, with days overdue, in the customer\'s currency.';
    }

    public function permissions(): array
    {
        return ['invoices.view', 'ledgers.view'];
    }

    public function filters(): array
    {
        return [new Filter('customer', 'Customer', options: DB::table('customers')->orderBy('name')->pluck('name', 'id')->all())];
    }

    public function columns(): array
    {
        return [
            new Column('invoice', 'Invoice', linkKey: 'url'), new Column('customer', 'Customer'), new Column('date', 'Date', 'date'), new Column('due', 'Due', 'date'),
            new Column('days', 'Days overdue', 'number'), new Column('bucket', 'Age'), new Column('total', 'Total', 'money', 'currency'),
            new Column('balance', 'Balance', 'money', 'currency', total: true), new Column('currency', 'Currency'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $paid = DB::table('invoice_allocations')->groupBy('invoice_id')->select('invoice_id', DB::raw('SUM(amount) as paid'));

        return DB::table('invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')->leftJoinSub($paid, 'p', 'p.invoice_id', '=', 'i.id')
            ->whereNotNull('i.posted_at')->when($filters['customer'] ?? null, fn ($q, $c) => $q->where('i.customer_id', $c))
            ->orderBy('i.due_date')->get(['i.id', 'i.number', 'c.name', 'i.invoice_date', 'i.due_date', 'i.total', 'i.currency_code', DB::raw('COALESCE(p.paid, 0) as paid')])
            ->map(function ($r) {
                $days = $r->due_date ? max(0, (int) Carbon::parse($r->due_date)->diffInDays(now(), false)) : 0;
                $bucket = match (true) {
                    $days === 0 => 'Current', $days <= 30 => '1–30', $days <= 60 => '31–60', $days <= 90 => '61–90', default => '90+'
                };

                return ['invoice' => $r->number, 'url' => route('delivery.invoices.show', $r->id), 'customer' => $r->name, 'date' => $r->invoice_date, 'due' => $r->due_date,
                    'days' => $days, 'bucket' => $bucket, 'total' => $r->total, 'balance' => Decimal::sub((string) $r->total, (string) $r->paid), 'currency' => $r->currency_code];
            })->filter(fn ($r) => Decimal::compare($r['balance'], '0') > 0)->values();
    }
}
