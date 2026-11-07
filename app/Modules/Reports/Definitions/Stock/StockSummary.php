<?php

namespace App\Modules\Reports\Definitions\Stock;

use App\Modules\Catalog\Models\Item;
use App\Modules\Inventory\Models\Location;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/** Current stock by variant: idle, in production, rejected, returned, reserved, free and value. */
class StockSummary extends Report
{
    public function key(): string
    {
        return 'stock-summary';
    }

    public function title(): string
    {
        return 'Stock summary and valuation';
    }

    public function group(): string
    {
        return 'Stock';
    }

    public function description(): string
    {
        return 'Idle, in production, rejected, returned and reserved stock by item, valued at lot cost.';
    }

    public function permissions(): array
    {
        return ['stock.view'];
    }

    public function filters(): array
    {
        return [new Filter('type', 'Item type', options: Item::TYPES), new Filter('location', 'Location', options: Location::query()->orderBy('name')->pluck('name', 'id')->all())];
    }

    public function columns(): array
    {
        $cols = [new Column('sku', 'SKU'), new Column('item', 'Item'), new Column('type', 'Type'), new Column('available', 'Idle (available)', 'qty', total: true),
            new Column('in_production', 'In production', 'qty', total: true), new Column('rejected', 'Rejected', 'qty', total: true), new Column('returned', 'Returned', 'qty', total: true),
            new Column('reserved', 'Reserved', 'qty', total: true), new Column('free', 'Free', 'qty', total: true)];
        if (auth()->user()?->can('cost.view')) {
            $cols[] = new Column('value', 'Value (PKR)', 'money', total: true);
        }

        return $cols;
    }

    protected function build(array $filters): iterable
    {
        $balances = DB::table('stock_balances as b')->join('item_variants as v', 'v.id', '=', 'b.item_variant_id')->join('items as i', 'i.id', '=', 'v.item_id')->join('lots as l', 'l.id', '=', 'b.lot_id')
            ->where('b.quantity', '>', 0)
            ->when($filters['type'] ?? null, fn ($q, $t) => $q->where('i.type', $t))
            ->when($filters['location'] ?? null, fn ($q, $l) => $q->where('b.location_id', $l))
            ->groupBy('b.item_variant_id', 'v.sku', 'v.label', 'i.name', 'i.type', 'b.status')
            ->get(['b.item_variant_id', 'v.sku', 'v.label', 'i.name', 'i.type', 'b.status', DB::raw('SUM(b.quantity) as qty'), DB::raw('SUM(b.quantity * COALESCE(l.unit_cost, 0)) as value')]);
        $reserved = DB::table('reservations')->where('status', 'active')->groupBy('item_variant_id')
            ->pluck(DB::raw('SUM(quantity - consumed_quantity) as q'), 'item_variant_id');

        return $balances->groupBy('item_variant_id')->map(function ($rows, $id) use ($reserved) {
            $first = $rows->first();
            $q = fn ($s) => Decimal::round((string) ($rows->firstWhere('status', $s)->qty ?? '0'), 4);
            $res = Decimal::round((string) ($reserved[$id] ?? '0'), 4);

            return ['sku' => $first->sku, 'item' => trim($first->name.' '.$first->label), 'type' => Item::TYPES[$first->type] ?? $first->type,
                'available' => $q('available'), 'in_production' => $q('in_production'), 'rejected' => $q('rejected'), 'returned' => $q('returned'),
                'reserved' => $res, 'free' => Decimal::sub($q('available'), $res, 4), 'value' => Decimal::round(Decimal::sum($rows->pluck('value')->map(fn ($v) => (string) $v), 4))];
        })->sortBy('sku')->values();
    }

    public function note(): ?string
    {
        return 'Value = quantity × lot unit cost. Pieces still inside a PTC carry no lot cost until released; their material is valued as In production.';
    }
}
