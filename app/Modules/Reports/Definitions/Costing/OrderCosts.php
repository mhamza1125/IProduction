<?php

namespace App\Modules\Reports\Definitions\Costing;

use App\Modules\Reports\Services\OrderCosting;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class OrderCosts extends Report
{
    public function key(): string
    {
        return 'order-costs';
    }

    public function title(): string
    {
        return 'Order cost, planned vs actual';
    }

    public function group(): string
    {
        return 'Costing';
    }

    public function description(): string
    {
        return 'Direct cost of each order against its planned cost, with cost per unit.';
    }

    public function permissions(): array
    {
        return ['cost.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange(), new Filter('customer', 'Customer', options: DB::table('customers')->orderBy('name')->pluck('name', 'id')->all())];
    }

    public function columns(): array
    {
        return [
            new Column('order', 'Order', linkKey: 'url'), new Column('customer', 'Customer'), new Column('date', 'Date', 'date'), new Column('status', 'Status'),
            new Column('delivered', 'Delivered (net)', 'qty'), new Column('planned', 'Planned (PKR)', 'money', total: true), new Column('actual', 'Actual (PKR)', 'money', total: true),
            new Column('variance', 'Variance', 'money', total: true), new Column('unit', 'Actual per unit', 'money'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $costing = app(OrderCosting::class);

        return SalesOrder::query()->with('customer')->where('status', '!=', 'draft')->where('status', '!=', 'cancelled')
            ->whereBetween('order_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->when($filters['customer'] ?? null, fn ($q, $c) => $q->where('customer_id', $c))->orderBy('order_date')->get()
            ->map(function ($o) use ($costing) {
                $c = $costing->forOrder($o);

                return ['order' => $o->number, 'url' => route('reports.order-cost', $o), 'customer' => $o->customer->name, 'date' => $o->order_date, 'status' => ucfirst(str_replace('_', ' ', $o->status)),
                    'delivered' => $c['delivered_qty'], 'planned' => $c['planned'], 'actual' => $c['actual'], 'variance' => Decimal::sub($c['actual'], $c['planned']),
                    'unit' => Decimal::compare($c['delivered_qty'], '0') > 0 ? Decimal::div($c['actual'], $c['delivered_qty'], 4) : null];
            });
    }

    public function note(): ?string
    {
        return 'Direct cost only (materials, wages, contractors, processing, rework, delivered at lot cost). Overhead such as non-production salaries, rent and utilities is not allocated.';
    }
}
