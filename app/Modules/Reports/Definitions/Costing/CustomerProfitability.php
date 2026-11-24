<?php

namespace App\Modules\Reports\Definitions\Costing;

use App\Modules\Reports\Services\OrderCosting;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Margin by customer for orders in a period. Revenue is what was delivered,
 * in the customer's currency; cost is the order's actual direct cost (PKR).
 * Foreign revenue is valued at the realized rate: PKR received ÷ foreign amount
 * over all posted currency conversions of that currency up to the period end.
 * A currency never converted shows no margin rather than a guessed one.
 */
class CustomerProfitability extends Report
{
    public function key(): string
    {
        return 'customer-profitability';
    }

    public function title(): string
    {
        return 'Customer profitability';
    }

    public function group(): string
    {
        return 'Costing';
    }

    public function description(): string
    {
        return 'Delivered revenue, actual cost and margin per customer; foreign revenue at the realized conversion rate.';
    }

    public function permissions(): array
    {
        return ['cost.view', 'profit.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange(now()->startOfYear()->toDateString())];
    }

    public function columns(): array
    {
        return [
            new Column('customer', 'Customer'), new Column('orders', 'Orders', 'number'), new Column('revenue', 'Revenue', 'money', 'currency'), new Column('currency', 'Currency'),
            new Column('rate', 'Realized rate', 'number'), new Column('revenue_pkr', 'Revenue (PKR)', 'money', total: true), new Column('cost', 'Cost (PKR)', 'money', total: true),
            new Column('margin', 'Margin (PKR)', 'money', total: true), new Column('margin_pct', 'Margin %', 'number'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $to = $this->to($filters)?->toDateString();
        $rates = DB::table('currency_conversions as c')->join('money_accounts as a', 'a.id', '=', 'c.from_account_id')
            ->whereNotNull('c.posted_at')->whereNull('c.cancelled_at')->when($to, fn ($q) => $q->where('c.conversion_date', '<=', $to))
            ->groupBy('a.currency_code')->get(['a.currency_code', DB::raw('SUM(c.pkr_received) as pkr'), DB::raw('SUM(c.from_amount) as foreign_amount')])
            ->filter(fn ($r) => (float) $r->foreign_amount > 0)
            ->mapWithKeys(fn ($r) => [$r->currency_code => Decimal::div((string) $r->pkr, (string) $r->foreign_amount, 4)]);

        $costing = app(OrderCosting::class);
        $byCustomer = [];
        $orders = SalesOrder::query()->with('customer')->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('order_date', [$this->from($filters)?->toDateString(), $to])->get();

        foreach ($orders as $order) {
            $c = $costing->forOrder($order);
            $key = $order->customer_id;
            $byCustomer[$key] ??= ['customer' => $order->customer->name, 'currency' => $c['currency'], 'orders' => 0, 'revenue' => '0', 'cost' => '0'];
            $byCustomer[$key]['orders']++;
            $byCustomer[$key]['revenue'] = Decimal::add($byCustomer[$key]['revenue'], $c['revenue']);
            $byCustomer[$key]['cost'] = Decimal::add($byCustomer[$key]['cost'], $c['actual']);
        }

        return collect($byCustomer)->map(function (array $r) use ($rates) {
            $rate = $r['currency'] === 'PKR' ? '1' : ($rates[$r['currency']] ?? null);
            $pkr = $rate === null ? null : Decimal::round(Decimal::mul($r['revenue'], $rate), 2);
            $margin = $pkr === null ? null : Decimal::sub($pkr, $r['cost']);

            return $r + [
                'rate' => $r['currency'] === 'PKR' ? null : $rate,
                'revenue_pkr' => $pkr,
                'margin' => $margin,
                'margin_pct' => $margin !== null && Decimal::compare((string) $pkr, '0') > 0 ? Decimal::div(Decimal::mul($margin, '100'), (string) $pkr, 1) : null,
            ];
        })->sortByDesc(fn ($r) => (float) ($r['margin'] ?? PHP_INT_MIN))->values();
    }
}
