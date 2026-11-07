<?php

namespace App\Modules\Reports\Definitions\Purchasing;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class BillsDue extends Report
{
    public function key(): string
    {
        return 'bills-due';
    }

    public function title(): string
    {
        return 'Vendor bills due';
    }

    public function group(): string
    {
        return 'Purchasing';
    }

    public function description(): string
    {
        return 'Posted vendor bills not fully paid.';
    }

    public function permissions(): array
    {
        return ['vendor_bills.view'];
    }

    public function filters(): array
    {
        return [new Filter('vendor', 'Vendor', options: DB::table('vendors')->whereNull('deleted_at')->orderBy('name')->pluck('name', 'id')->all())];
    }

    public function columns(): array
    {
        return [
            new Column('bill', 'Bill', linkKey: 'url'), new Column('vendor', 'Vendor'), new Column('date', 'Date', 'date'), new Column('due', 'Due', 'date'),
            new Column('total', 'Total', 'money'), new Column('paid', 'Paid', 'money'), new Column('balance', 'Balance', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('vendor_bills as b')->join('vendors as v', 'v.id', '=', 'b.vendor_id')->whereNotNull('b.posted_at')->whereColumn('b.paid_amount', '<', 'b.total')
            ->when($filters['vendor'] ?? null, fn ($q, $v) => $q->where('b.vendor_id', $v))->orderBy('b.due_date')
            ->get(['b.id', 'b.number', 'v.name', 'b.bill_date', 'b.due_date', 'b.total', 'b.paid_amount', DB::raw('b.total - b.paid_amount as balance')])
            ->map(fn ($r) => ['bill' => $r->number, 'url' => route('purchasing.bills.show', $r->id), 'vendor' => $r->name, 'date' => $r->bill_date, 'due' => $r->due_date,
                'total' => $r->total, 'paid' => $r->paid_amount, 'balance' => $r->balance]);
    }
}
