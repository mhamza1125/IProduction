<?php

namespace App\Modules\Reports\Definitions\Purchasing;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class ReceiptVariances extends Report
{
    public function key(): string
    {
        return 'receipt-variances';
    }

    public function title(): string
    {
        return 'Receipt variances and over-receipts';
    }

    public function group(): string
    {
        return 'Purchasing';
    }

    public function description(): string
    {
        return 'Receipt lines where accepted differs from ordered, rejected pieces, and over-receipt decisions.';
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
            new Column('grn', 'GRN', linkKey: 'url'), new Column('date', 'Date', 'date'), new Column('vendor', 'Vendor'), new Column('sku', 'Item'),
            new Column('ordered', 'Ordered', 'qty'), new Column('received', 'Received', 'qty'), new Column('accepted', 'Accepted', 'qty'), new Column('rejected', 'Rejected', 'qty'),
            new Column('action', 'Over-receipt'),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('goods_receipt_lines as l')->join('goods_receipts as g', 'g.id', '=', 'l.goods_receipt_id')->join('vendors as v', 'v.id', '=', 'g.vendor_id')
            ->join('item_variants as iv', 'iv.id', '=', 'l.item_variant_id')->whereNotNull('g.posted_at')
            ->whereBetween('g.receipt_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->where(fn ($q) => $q->whereNotNull('l.over_receipt_action')->orWhere('l.rejected_qty', '>', 0)->orWhereColumn('l.received_qty', '!=', DB::raw('l.ordered_qty - l.previously_accepted_qty')))
            ->orderBy('g.receipt_date')
            ->get(['g.id', 'g.number', 'g.receipt_date', 'v.name', 'iv.sku', 'l.ordered_qty', 'l.received_qty', 'l.accepted_qty', 'l.rejected_qty', 'l.over_receipt_action'])
            ->map(fn ($r) => ['grn' => $r->number, 'url' => route('purchasing.receipts.show', $r->id), 'date' => $r->receipt_date, 'vendor' => $r->name, 'sku' => $r->sku,
                'ordered' => $r->ordered_qty, 'received' => $r->received_qty, 'accepted' => $r->accepted_qty, 'rejected' => $r->rejected_qty,
                'action' => $r->over_receipt_action ? str_replace('_', ' ', ucfirst($r->over_receipt_action)) : '']);
    }
}
