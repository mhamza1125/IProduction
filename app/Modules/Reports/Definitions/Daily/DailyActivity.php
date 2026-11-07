<?php

namespace App\Modules\Reports\Definitions\Daily;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class DailyActivity extends Report
{
    public function key(): string
    {
        return 'daily-activity';
    }

    public function title(): string
    {
        return 'Daily activity';
    }

    public function group(): string
    {
        return 'Daily';
    }

    public function description(): string
    {
        return 'Issues, receipts, production, rejections, deliveries, purchases and payments for a day or range.';
    }

    public function filters(): array
    {
        return [...$this->dateRange(now()->toDateString()), new Filter('kind', 'Activity', options: [
            'issues' => 'Material issues', 'production' => 'Production receipts', 'rejections' => 'Rejections', 'receipts' => 'Goods received',
            'purchases' => 'Purchase orders', 'deliveries' => 'Deliveries', 'payments' => 'Payments', 'customer_receipts' => 'Customer receipts',
        ])];
    }

    public function columns(): array
    {
        return [new Column('date', 'Date', 'date'), new Column('kind', 'Activity'), new Column('number', 'Document', linkKey: 'url'), new Column('party', 'Party / item'),
            new Column('qty', 'Quantity', 'qty'), new Column('amount', 'Amount', 'money', 'currency')];
    }

    protected function build(array $filters): iterable
    {
        $from = $this->from($filters)?->toDateString();
        $to = $this->to($filters)?->toDateString();
        $want = fn (string $k) => empty($filters['kind']) || $filters['kind'] === $k;
        $rows = collect();

        $ptcTx = fn (array $types, string $label) => DB::table('ptc_transactions as t')->join('ptcs as p', 'p.id', '=', 't.ptc_id')->join('item_variants as v', 'v.id', '=', 't.item_variant_id')
            ->whereIn('t.type', $types)->whereBetween('t.transaction_date', [$from, $to])
            ->get(['t.transaction_date as date', 'p.id', 'p.number', 'v.sku', 't.quantity'])
            ->map(fn ($r) => ['date' => $r->date, 'kind' => $label, 'number' => $r->number, 'url' => route('production.ptcs.show', $r->id), 'party' => $r->sku, 'qty' => $r->quantity, 'amount' => null, 'currency' => null]);
        if ($want('issues')) {
            $rows = $rows->merge($ptcTx(['material_issue', 'extra_issue', 'stage_issue'], 'Issue'));
        }
        if ($want('production')) {
            $rows = $rows->merge($ptcTx(['stage_receive', 'release'], 'Production'));
        }
        if ($want('rejections')) {
            $rows = $rows->merge(DB::table('rejections as r')->join('item_variants as v', 'v.id', '=', 'r.item_variant_id')->whereBetween('r.rejection_date', [$from, $to])
                ->get(['r.rejection_date as date', 'r.id', 'r.number', 'v.sku', 'r.quantity'])
                ->map(fn ($r) => ['date' => $r->date, 'kind' => 'Rejection', 'number' => $r->number, 'url' => route('production.rejections.show', $r->id), 'party' => $r->sku, 'qty' => $r->quantity, 'amount' => null, 'currency' => null]));
        }
        if ($want('receipts')) {
            $rows = $rows->merge(DB::table('goods_receipts as g')->join('vendors as v', 'v.id', '=', 'g.vendor_id')->whereNotNull('g.posted_at')->whereBetween('g.receipt_date', [$from, $to])
                ->get(['g.receipt_date as date', 'g.id', 'g.number', 'v.name', 'g.total_value'])
                ->map(fn ($r) => ['date' => $r->date, 'kind' => 'Goods received', 'number' => $r->number, 'url' => route('purchasing.receipts.show', $r->id), 'party' => $r->name, 'qty' => null, 'amount' => $r->total_value, 'currency' => 'PKR']));
        }
        if ($want('purchases')) {
            $rows = $rows->merge(DB::table('purchase_orders as o')->join('vendors as v', 'v.id', '=', 'o.vendor_id')->whereNotNull('o.approved_at')->whereBetween('o.order_date', [$from, $to])
                ->get(['o.order_date as date', 'o.id', 'o.number', 'v.name', 'o.total'])
                ->map(fn ($r) => ['date' => $r->date, 'kind' => 'Purchase order', 'number' => $r->number, 'url' => route('purchasing.orders.show', $r->id), 'party' => $r->name, 'qty' => null, 'amount' => $r->total, 'currency' => 'PKR']));
        }
        if ($want('deliveries')) {
            $rows = $rows->merge(DB::table('deliveries as d')->join('customers as c', 'c.id', '=', 'd.customer_id')->where('d.status', 'dispatched')->whereBetween('d.delivery_date', [$from, $to])
                ->get(['d.delivery_date as date', 'd.id', 'd.number', 'c.name'])
                ->map(fn ($r) => ['date' => $r->date, 'kind' => 'Delivery', 'number' => $r->number, 'url' => route('delivery.deliveries.show', $r->id), 'party' => $r->name,
                    'qty' => DB::table('delivery_lines')->where('delivery_id', $r->id)->sum('quantity'), 'amount' => null, 'currency' => null]));
        }
        if ($want('payments')) {
            $rows = $rows->merge(DB::table('payments as p')->whereNotNull('p.posted_at')->whereBetween('p.payment_date', [$from, $to])
                ->get(['p.payment_date as date', 'p.id', 'p.number', 'p.payee_kind', 'p.amount'])
                ->map(fn ($r) => ['date' => $r->date, 'kind' => 'Payment', 'number' => $r->number, 'url' => route('finance.payments.show', $r->id), 'party' => ucfirst($r->payee_kind), 'qty' => null, 'amount' => $r->amount, 'currency' => 'PKR']));
        }
        if ($want('customer_receipts')) {
            $rows = $rows->merge(DB::table('receipts as r')->join('customers as c', 'c.id', '=', 'r.customer_id')->whereNotNull('r.posted_at')->whereBetween('r.receipt_date', [$from, $to])
                ->get(['r.receipt_date as date', 'r.id', 'r.number', 'c.name', 'r.amount', 'r.currency_code'])
                ->map(fn ($r) => ['date' => $r->date, 'kind' => 'Customer receipt', 'number' => $r->number, 'url' => route('finance.receipts.show', $r->id), 'party' => $r->name, 'qty' => null, 'amount' => $r->amount, 'currency' => $r->currency_code]));
        }

        return $rows->sortBy(fn ($r) => $r['date'].$r['number'])->values();
    }
}
