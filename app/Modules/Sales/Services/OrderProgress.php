<?php

namespace App\Modules\Sales\Services;

use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Support\Decimal;

/**
 * How deliveries, invoices and returns update an order line's progress.
 * Other modules call this instead of writing sales tables.
 */
class OrderProgress
{
    public function __construct(private readonly SalesOrders $orders) {}

    public function delivered(SalesOrderLine $line, string $quantity): void
    {
        $line = SalesOrderLine::query()->lockForUpdate()->findOrFail($line->id);
        $line->forceFill(['delivered_qty' => Decimal::add($line->delivered_qty, $quantity, 4)])->save();
        $this->orders->refreshStatus($line->order()->firstOrFail());
    }

    public function invoiced(SalesOrderLine $line, string $quantity): void
    {
        $line = SalesOrderLine::query()->lockForUpdate()->findOrFail($line->id);
        $line->forceFill(['invoiced_qty' => Decimal::add($line->invoiced_qty, $quantity, 4)])->save();
    }

    /**
     * Goods came back: delivered quantity goes down. With a replacement the
     * line reopens; without one the returned quantity is closed.
     */
    public function returned(SalesOrderLine $line, string $quantity, bool $replacement): void
    {
        $line = SalesOrderLine::query()->lockForUpdate()->findOrFail($line->id);
        $line->forceFill([
            'returned_qty' => Decimal::add($line->returned_qty, $quantity, 4),
            'delivered_qty' => Decimal::sub($line->delivered_qty, $quantity, 4),
            'closed_qty' => $replacement ? $line->closed_qty : Decimal::add($line->closed_qty, $quantity, 4),
        ])->save();

        $order = SalesOrder::query()->lockForUpdate()->findOrFail($line->sales_order_id);
        if ($replacement && $order->status === 'delivered') {
            $order->forceFill(['status' => 'partly_delivered'])->save();
        }
        $this->orders->refreshStatus($order);
    }
}
