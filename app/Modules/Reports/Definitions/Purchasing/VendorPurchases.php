<?php

namespace App\Modules\Reports\Definitions\Purchasing;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class VendorPurchases extends Report
{
    public function key(): string
    {
        return 'vendor-purchases';
    }

    public function title(): string
    {
        return 'Vendor purchases';
    }

    public function group(): string
    {
        return 'Purchasing';
    }

    public function description(): string
    {
        return 'Goods received by vendor, at the PKR value posted.';
    }

    public function permissions(): array
    {
        return ['goods_receipts.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('vendor', 'Vendor'), new Column('receipts', 'Receipts', 'number'), new Column('value', 'Value (PKR)', 'money', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('goods_receipts as g')->join('vendors as v', 'v.id', '=', 'g.vendor_id')->whereNotNull('g.posted_at')
            ->whereBetween('g.receipt_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('v.name')->orderByDesc(DB::raw('SUM(g.total_value)'))
            ->get(['v.name as vendor', DB::raw('COUNT(*) as receipts'), DB::raw('SUM(g.total_value) as value')])->map(fn ($r) => (array) $r);
    }
}
