<?php

namespace App\Modules\Reports\Definitions\Sales;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class OrderProgress extends Report
{
    public function key(): string
    {
        return 'order-progress';
    }

    public function title(): string
    {
        return 'Open orders and progress';
    }

    public function group(): string
    {
        return 'Sales';
    }

    public function description(): string
    {
        return 'Every open order line: ordered, delivered, invoiced, returned and remaining.';
    }

    public function permissions(): array
    {
        return ['sales_orders.view'];
    }

    public function filters(): array
    {
        return [new Filter('customer', 'Customer', options: DB::table('customers')->orderBy('name')->pluck('name', 'id')->all())];
    }

    public function columns(): array
    {
        return [
            new Column('order', 'Order', linkKey: 'url'), new Column('customer', 'Customer'), new Column('sku', 'Item'), new Column('due', 'Due', 'date'),
            new Column('ordered', 'Ordered', 'qty'), new Column('delivered', 'Delivered', 'qty'), new Column('invoiced', 'Invoiced', 'qty'),
            new Column('returned', 'Returned', 'qty'), new Column('remaining', 'Remaining', 'qty', total: true), new Column('late', 'Late'),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('sales_order_lines as l')->join('sales_orders as o', 'o.id', '=', 'l.sales_order_id')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->join('item_variants as v', 'v.id', '=', 'l.item_variant_id')
            ->whereIn('o.status', ['approved', 'in_planning', 'in_progress', 'partly_delivered'])
            ->when($filters['customer'] ?? null, fn ($q, $c) => $q->where('o.customer_id', $c))
            ->orderByRaw('COALESCE(l.due_date, o.due_date)')->orderBy('o.number')
            ->get(['o.id', 'o.number', 'c.name', 'v.sku', DB::raw('COALESCE(l.due_date, o.due_date) as due'), 'l.quantity', 'l.delivered_qty', 'l.invoiced_qty', 'l.returned_qty', 'l.closed_qty'])
            ->map(function ($r) {
                $remaining = Decimal::sub(Decimal::sub((string) $r->quantity, (string) $r->delivered_qty, 4), (string) $r->closed_qty, 4);

                return ['order' => $r->number, 'url' => route('sales.orders.show', $r->id), 'customer' => $r->name, 'sku' => $r->sku, 'due' => $r->due, 'ordered' => $r->quantity,
                    'delivered' => $r->delivered_qty, 'invoiced' => $r->invoiced_qty, 'returned' => $r->returned_qty, 'remaining' => $remaining,
                    'late' => $r->due && $r->due < now()->toDateString() && Decimal::compare($remaining, '0') > 0 ? 'Late' : ''];
            })->filter(fn ($r) => Decimal::compare($r['remaining'], '0') > 0)->values();
    }
}
