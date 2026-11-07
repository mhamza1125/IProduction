<?php

namespace App\Modules\Reports\Definitions\Stock;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class ContractorStock extends Report
{
    public function key(): string
    {
        return 'contractor-stock';
    }

    public function title(): string
    {
        return 'Stock held by contractors';
    }

    public function group(): string
    {
        return 'Contractors';
    }

    public function description(): string
    {
        return 'Company stock at contractor locations (still company stock).';
    }

    public function permissions(): array
    {
        return ['stock.view'];
    }

    public function columns(): array
    {
        $cols = [new Column('contractor', 'Contractor'), new Column('sku', 'SKU'), new Column('stage', 'Stage'), new Column('status', 'Status'), new Column('lot', 'Lot'), new Column('quantity', 'Quantity', 'qty', total: true)];
        if (auth()->user()?->can('cost.view')) {
            $cols[] = new Column('value', 'Value (PKR)', 'money', total: true);
        }

        return $cols;
    }

    protected function build(array $filters): iterable
    {
        return DB::table('stock_balances as b')->join('locations as loc', 'loc.id', '=', 'b.location_id')->join('contractors as c', 'c.id', '=', 'loc.contractor_id')
            ->join('item_variants as v', 'v.id', '=', 'b.item_variant_id')->join('lots as l', 'l.id', '=', 'b.lot_id')->leftJoin('stages as s', 's.id', '=', 'b.stage_id')
            ->where('b.quantity', '>', 0)->orderBy('c.name')->orderBy('v.sku')
            ->get(['c.name as contractor', 'v.sku', 's.name as stage', 'b.status', 'l.lot_number as lot', 'b.quantity', DB::raw('ROUND(b.quantity * COALESCE(l.unit_cost, 0), 2) as value')])
            ->map(fn ($r) => (array) $r);
    }
}
