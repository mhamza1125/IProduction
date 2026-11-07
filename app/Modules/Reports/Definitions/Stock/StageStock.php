<?php

namespace App\Modules\Reports\Definitions\Stock;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class StageStock extends Report
{
    public function key(): string
    {
        return 'stage-stock';
    }

    public function title(): string
    {
        return 'Stage-wise stock';
    }

    public function group(): string
    {
        return 'Stock';
    }

    public function description(): string
    {
        return 'Part-made stock at each stage, by status (e.g. Glove-Red-M, Stage 1 = 40).';
    }

    public function permissions(): array
    {
        return ['stock.view'];
    }

    public function columns(): array
    {
        return [new Column('sku', 'SKU'), new Column('stage', 'Stage'), new Column('location', 'Location'), new Column('available', 'Available', 'qty', total: true),
            new Column('in_production', 'In production', 'qty', total: true), new Column('rejected', 'Rejected', 'qty', total: true), new Column('returned', 'Returned', 'qty', total: true)];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('stock_balances as b')->join('item_variants as v', 'v.id', '=', 'b.item_variant_id')->join('stages as s', 's.id', '=', 'b.stage_id')->join('locations as loc', 'loc.id', '=', 'b.location_id')
            ->whereNotNull('b.stage_id')->where('b.quantity', '>', 0)
            ->groupBy('v.sku', 's.name', 's.sort_order', 'loc.name')
            ->orderBy('v.sku')->orderBy('s.sort_order')
            ->get(['v.sku', 's.name as stage', 'loc.name as location',
                DB::raw("SUM(CASE WHEN b.status = 'available' THEN b.quantity ELSE 0 END) as available"),
                DB::raw("SUM(CASE WHEN b.status = 'in_production' THEN b.quantity ELSE 0 END) as in_production"),
                DB::raw("SUM(CASE WHEN b.status = 'rejected' THEN b.quantity ELSE 0 END) as rejected"),
                DB::raw("SUM(CASE WHEN b.status = 'returned' THEN b.quantity ELSE 0 END) as returned")])
            ->map(fn ($r) => (array) $r);
    }
}
