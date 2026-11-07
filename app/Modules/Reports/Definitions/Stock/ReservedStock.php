<?php

namespace App\Modules\Reports\Definitions\Stock;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Support\Facades\DB;

class ReservedStock extends Report
{
    public function key(): string
    {
        return 'reserved-stock';
    }

    public function title(): string
    {
        return 'Reserved stock';
    }

    public function group(): string
    {
        return 'Stock';
    }

    public function description(): string
    {
        return 'Stock held for sales order lines.';
    }

    public function permissions(): array
    {
        return ['stock.view'];
    }

    public function columns(): array
    {
        return [new Column('order', 'Order', linkKey: 'url'), new Column('customer', 'Customer'), new Column('sku', 'SKU'), new Column('lot', 'Lot'),
            new Column('quantity', 'Reserved', 'qty', total: true), new Column('since', 'Since', 'date')];
    }

    protected function build(array $filters): iterable
    {
        $type = (new SalesOrderLine)->getMorphClass();

        return DB::table('reservations as r')->join('item_variants as v', 'v.id', '=', 'r.item_variant_id')->leftJoin('lots as l', 'l.id', '=', 'r.lot_id')
            ->join('sales_order_lines as sl', fn ($j) => $j->on('sl.id', '=', 'r.demand_id')->where('r.demand_type', '=', $type))
            ->join('sales_orders as o', 'o.id', '=', 'sl.sales_order_id')->join('customers as c', 'c.id', '=', 'o.customer_id')
            ->where('r.status', 'active')->orderBy('o.number')
            ->get(['o.id as order_id', 'o.number as order', 'c.name as customer', 'v.sku', 'l.lot_number as lot', DB::raw('r.quantity - r.consumed_quantity as quantity'), 'r.created_at as since'])
            ->map(fn ($r) => (array) $r + ['url' => route('sales.orders.show', $r->order_id)]);
    }
}
