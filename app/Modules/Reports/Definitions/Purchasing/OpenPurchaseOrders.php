<?php

namespace App\Modules\Reports\Definitions\Purchasing;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class OpenPurchaseOrders extends Report
{
    public function key(): string
    {
        return 'open-pos';
    }

    public function title(): string
    {
        return 'Open purchase orders';
    }

    public function group(): string
    {
        return 'Purchasing';
    }

    public function description(): string
    {
        return 'Purchase order lines still to be received.';
    }

    public function permissions(): array
    {
        return ['purchase_orders.view'];
    }

    public function filters(): array
    {
        return [new Filter('vendor', 'Vendor', options: DB::table('vendors')->whereNull('deleted_at')->orderBy('name')->pluck('name', 'id')->all())];
    }

    public function columns(): array
    {
        return [
            new Column('po', 'PO', linkKey: 'url'), new Column('vendor', 'Vendor'), new Column('sku', 'Item'), new Column('expected', 'Expected', 'date'),
            new Column('ordered', 'Ordered', 'qty'), new Column('accepted', 'Accepted', 'qty'), new Column('outstanding', 'Outstanding', 'qty', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('purchase_order_lines as l')->join('purchase_orders as o', 'o.id', '=', 'l.purchase_order_id')->join('vendors as v', 'v.id', '=', 'o.vendor_id')
            ->join('item_variants as iv', 'iv.id', '=', 'l.item_variant_id')->whereIn('o.status', ['approved', 'sent', 'partly_received'])
            ->when($filters['vendor'] ?? null, fn ($q, $v) => $q->where('o.vendor_id', $v))->orderBy('l.expected_date')
            ->get(['o.id', 'o.number', 'v.name', 'iv.sku', 'l.expected_date', 'l.quantity', 'l.accepted_qty', DB::raw('l.quantity - l.accepted_qty - l.closed_short_qty + l.returned_qty as outstanding')])
            ->filter(fn ($r) => Decimal::compare((string) $r->outstanding, '0') > 0)
            ->map(fn ($r) => ['po' => $r->number, 'url' => route('purchasing.orders.show', $r->id), 'vendor' => $r->name, 'sku' => $r->sku, 'expected' => $r->expected_date,
                'ordered' => $r->quantity, 'accepted' => $r->accepted_qty, 'outstanding' => $r->outstanding])->values();
    }
}
