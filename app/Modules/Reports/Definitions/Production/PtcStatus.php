<?php

namespace App\Modules\Reports\Definitions\Production;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PtcStatus extends Report
{
    public function key(): string
    {
        return 'ptc-status';
    }

    public function title(): string
    {
        return 'PTC status and delays';
    }

    public function group(): string
    {
        return 'Production';
    }

    public function description(): string
    {
        return 'Open PTCs with planned, released and days past target.';
    }

    public function permissions(): array
    {
        return ['ptcs.view'];
    }

    public function filters(): array
    {
        return [new Filter('late', 'Show', options: ['late' => 'Delayed only'])];
    }

    public function columns(): array
    {
        return [
            new Column('ptc', 'PTC', linkKey: 'url'), new Column('sku', 'Item'), new Column('order', 'Order'), new Column('status', 'Status'), new Column('planned', 'Planned', 'qty'),
            new Column('released', 'Released', 'qty'), new Column('target', 'Target', 'date'), new Column('delay', 'Days late', 'number'),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('ptcs as p')->join('item_variants as v', 'v.id', '=', 'p.item_variant_id')->leftJoin('sales_order_lines as sl', 'sl.id', '=', 'p.sales_order_line_id')
            ->leftJoin('sales_orders as o', 'o.id', '=', 'sl.sales_order_id')->whereIn('p.status', ['approved', 'in_progress', 'on_hold'])
            ->when(($filters['late'] ?? null) === 'late', fn ($q) => $q->where('p.target_date', '<', now()->toDateString()))->orderBy('p.target_date')
            ->get(['p.id', 'p.number', 'v.sku', 'o.number as order', 'p.status', 'p.planned_qty', 'p.released_qty', 'p.target_date'])
            ->map(fn ($r) => ['ptc' => $r->number, 'url' => route('production.ptcs.show', $r->id), 'sku' => $r->sku, 'order' => $r->order ?? 'Stock', 'status' => ucfirst(str_replace('_', ' ', $r->status)),
                'planned' => $r->planned_qty, 'released' => $r->released_qty, 'target' => $r->target_date,
                'delay' => $r->target_date && $r->target_date < now()->toDateString() ? (int) Carbon::parse($r->target_date)->diffInDays(now()) : null]);
    }
}
