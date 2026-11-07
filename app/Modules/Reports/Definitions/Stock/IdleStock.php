<?php

namespace App\Modules\Reports\Definitions\Stock;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class IdleStock extends Report
{
    public function key(): string
    {
        return 'idle-stock';
    }

    public function title(): string
    {
        return 'Idle stock';
    }

    public function group(): string
    {
        return 'Stock';
    }

    public function description(): string
    {
        return 'Available stock with no movement for a number of days.';
    }

    public function permissions(): array
    {
        return ['stock.view'];
    }

    public function filters(): array
    {
        return [new Filter('days', 'No movement for', options: ['30' => '30 days', '60' => '60 days', '90' => '90 days', '180' => '180 days', '365' => '1 year'], default: '90')];
    }

    public function columns(): array
    {
        return [new Column('sku', 'SKU'), new Column('lot', 'Lot', linkKey: 'url'), new Column('location', 'Location'), new Column('quantity', 'Quantity', 'qty', total: true),
            new Column('last', 'Last movement', 'date')];
    }

    protected function build(array $filters): iterable
    {
        $cutoff = now()->subDays((int) ($filters['days'] ?: 90))->toDateString();
        $last = DB::table('stock_movements')->groupBy('lot_id')->select('lot_id', DB::raw('MAX(movement_date) as last'));

        return DB::table('stock_balances as b')->join('item_variants as v', 'v.id', '=', 'b.item_variant_id')->join('lots as l', 'l.id', '=', 'b.lot_id')
            ->join('locations as loc', 'loc.id', '=', 'b.location_id')->joinSub($last, 'm', 'm.lot_id', '=', 'b.lot_id')
            ->where('b.status', 'available')->where('b.quantity', '>', 0)->where('m.last', '<', $cutoff)->orderBy('m.last')
            ->get(['v.sku', 'l.id as lot_id', 'l.lot_number as lot', 'loc.name as location', 'b.quantity', 'm.last'])
            ->map(fn ($r) => (array) $r + ['url' => route('inventory.lots.show', $r->lot_id)]);
    }
}
