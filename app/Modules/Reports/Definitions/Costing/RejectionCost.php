<?php

namespace App\Modules\Reports\Definitions\Costing;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class RejectionCost extends Report
{
    public function key(): string
    {
        return 'rejection-cost';
    }

    public function title(): string
    {
        return 'Rejection cost';
    }

    public function group(): string
    {
        return 'Costing';
    }

    public function description(): string
    {
        return 'Rejected pieces valued at lot cost, with the decisions taken.';
    }

    public function permissions(): array
    {
        return ['cost.view', 'rejections.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('rejection', 'Rejection', linkKey: 'url'), new Column('date', 'Date', 'date'), new Column('sku', 'Item'), new Column('reason', 'Reason'),
            new Column('quantity', 'Pieces', 'qty', total: true), new Column('value', 'Value at lot cost', 'money', total: true), new Column('recovered', 'Recovered charges', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        $recovered = DB::table('rejection_decisions')->where('decision', 'recover')->whereNotNull('posted_at')->groupBy('rejection_id')->select('rejection_id', DB::raw('SUM(amount) as recovered'));

        return DB::table('rejections as r')->join('item_variants as v', 'v.id', '=', 'r.item_variant_id')->join('lots as l', 'l.id', '=', 'r.lot_id')->leftJoinSub($recovered, 'd', 'd.rejection_id', '=', 'r.id')
            ->whereBetween('r.rejection_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])->orderBy('r.rejection_date')
            ->get(['r.id', 'r.number', 'r.rejection_date', 'v.sku', 'r.reason', 'r.quantity', DB::raw('ROUND(r.quantity * COALESCE(l.unit_cost, 0), 2) as value'), DB::raw('COALESCE(d.recovered, 0) as recovered')])
            ->map(fn ($r) => ['rejection' => $r->number, 'url' => route('production.rejections.show', $r->id), 'date' => $r->rejection_date, 'sku' => $r->sku, 'reason' => $r->reason,
                'quantity' => $r->quantity, 'value' => $r->value, 'recovered' => $r->recovered]);
    }
}
