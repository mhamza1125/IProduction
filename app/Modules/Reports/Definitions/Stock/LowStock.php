<?php

namespace App\Modules\Reports\Definitions\Stock;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class LowStock extends Report
{
    public function key(): string
    {
        return 'low-stock';
    }

    public function title(): string
    {
        return 'Low stock';
    }

    public function group(): string
    {
        return 'Stock';
    }

    public function description(): string
    {
        return 'Items whose free stock is below their minimum level.';
    }

    public function permissions(): array
    {
        return ['stock.view'];
    }

    public function columns(): array
    {
        return [new Column('sku', 'SKU'), new Column('item', 'Item'), new Column('minimum', 'Minimum', 'qty'), new Column('free', 'Free', 'qty'), new Column('short', 'Short by', 'qty', total: true)];
    }

    protected function build(array $filters): iterable
    {
        $available = DB::table('stock_balances')->where('status', 'available')->groupBy('item_variant_id')->pluck(DB::raw('SUM(quantity)'), 'item_variant_id');
        $reserved = DB::table('reservations')->where('status', 'active')->groupBy('item_variant_id')->pluck(DB::raw('SUM(quantity - consumed_quantity)'), 'item_variant_id');

        return DB::table('item_variants as v')->join('items as i', 'i.id', '=', 'v.item_id')->whereNull('v.deleted_at')->where('v.is_active', true)
            ->whereRaw('COALESCE(v.min_stock, i.min_stock) IS NOT NULL')
            ->get(['v.id', 'v.sku', 'i.name', 'v.label', DB::raw('COALESCE(v.min_stock, i.min_stock) as minimum')])
            ->map(function ($r) use ($available, $reserved) {
                $free = Decimal::sub((string) ($available[$r->id] ?? '0'), (string) ($reserved[$r->id] ?? '0'), 4);

                return ['sku' => $r->sku, 'item' => trim($r->name.' '.$r->label), 'minimum' => $r->minimum, 'free' => $free, 'short' => Decimal::sub((string) $r->minimum, $free, 4)];
            })
            ->filter(fn ($r) => Decimal::compare($r['short'], '0') > 0)->sortByDesc(fn ($r) => (float) $r['short'])->values();
    }
}
