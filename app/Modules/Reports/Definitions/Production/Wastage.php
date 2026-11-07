<?php

namespace App\Modules\Reports\Definitions\Production;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class Wastage extends Report
{
    public function key(): string
    {
        return 'wastage';
    }

    public function title(): string
    {
        return 'Wastage';
    }

    public function group(): string
    {
        return 'Production';
    }

    public function description(): string
    {
        return 'Material recorded as wastage in PTCs and contractor jobs.';
    }

    public function permissions(): array
    {
        return ['ptcs.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('date', 'Date', 'date'), new Column('document', 'Document'), new Column('sku', 'Material'), new Column('quantity', 'Quantity', 'qty'),
            new Column('amount', 'Cost (PKR)', 'money', total: true), new Column('reason', 'Reason'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $range = [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()];
        $ptc = DB::table('ptc_transactions as t')->join('ptcs as p', 'p.id', '=', 't.ptc_id')->join('item_variants as v', 'v.id', '=', 't.item_variant_id')
            ->where('t.type', 'wastage')->whereBetween('t.transaction_date', $range)->get(['t.transaction_date as date', 'p.number as document', 'v.sku', 't.quantity', 't.amount', 't.reason']);
        $jobs = DB::table('contractor_job_transactions as t')->join('contractor_jobs as j', 'j.id', '=', 't.contractor_job_id')->join('item_variants as v', 'v.id', '=', 't.item_variant_id')
            ->where('t.type', 'wastage')->whereBetween('t.transaction_date', $range)->get(['t.transaction_date as date', 'j.number as document', 'v.sku', 't.quantity', 't.amount', 't.reason']);
        $canCost = auth()->user()?->can('cost.view');

        return $ptc->merge($jobs)->sortBy('date')->values()->map(fn ($r) => array_merge((array) $r, $canCost ? [] : ['amount' => null]));
    }
}
