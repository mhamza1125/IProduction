<?php

namespace App\Modules\Sales\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\People\Models\Customer;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Models\SupplyAllocation;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sales order rules (specification, Module 5):
 *  - currency comes from the customer;
 *  - an approved order is edited only with permission, and quantities never
 *    fall below what was delivered; lines with stock activity cannot be deleted;
 *  - an order with no posted activity can be cancelled; otherwise its remaining
 *    quantity is closed with a reason; reservations are released either way.
 */
class SalesOrders
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly StockLedger $stock,
        private readonly DocumentWorkflow $workflow,
    ) {}

    /**
     * @param  array<string, mixed>  $header  customer_id, order_date, due_date, customer_po_number, shipping_address, payment_terms, notes, sells_rejected_stock
     * @param  list<array<string, mixed>>  $lines  id?, item_variant_id, description, quantity, unit_price, tax_rate, due_date
     */
    public function save(?SalesOrder $order, array $header, array $lines): SalesOrder
    {
        if ($order && ! $order->isDraft()) {
            if (! auth()->user()?->can('sales_orders.override')) {
                throw new DocumentException("{$order->number} is approved. Editing it needs permission.");
            }
            if (! $order->isOpen()) {
                throw new DocumentException("{$order->number} is {$order->status} and cannot be edited.");
            }
        }

        return DB::transaction(function () use ($order, $header, $lines) {
            $customer = Customer::query()->findOrFail($header['customer_id']);
            $isNew = $order === null;

            if ($order && $order->customer_id !== $customer->id && ! $order->isDraft()) {
                throw new DocumentException('The customer of an approved order cannot change.');
            }

            $order ??= new SalesOrder([
                'number' => $this->numbers->next('sales_order', Carbon::parse($header['order_date'])),
                'created_by' => auth()->id(),
            ]);

            $attributes = $header + ['currency_code' => $customer->currency_code];
            if (! $order->isDraft() && ! $isNew) {
                // Only these header fields may change after approval.
                $attributes = array_intersect_key($attributes, array_flip(['due_date', 'customer_po_number', 'shipping_address', 'payment_terms', 'notes']));
            }
            $order->fill($attributes)->save();

            $existing = $order->lines()->get()->keyBy('id');
            $keep = [];
            $total = '0';

            foreach ($lines as $i => $line) {
                $variant = ItemVariant::query()->with('item')->findOrFail($line['item_variant_id']);
                $model = isset($line['id']) && $existing->has($line['id']) ? $existing[$line['id']] : new SalesOrderLine(['sales_order_id' => $order->id]);

                if ($model->exists && Decimal::compare($line['quantity'], Decimal::add($model->delivered_qty, $model->closed_qty, 4)) < 0) {
                    throw new DocumentException('Line '.($i + 1).": quantity cannot be below the {$model->delivered_qty} already delivered.");
                }
                if ($model->exists && $model->item_variant_id !== $variant->id && $this->hasActivity($model)) {
                    throw new DocumentException('Line '.($i + 1).' has stock activity; its item cannot change.');
                }

                $lineTotal = Decimal::mul($line['quantity'], $line['unit_price']);
                $total = Decimal::add($total, $lineTotal);

                $model->fill([
                    'item_variant_id' => $variant->id,
                    'description' => $line['description'] ?: null,
                    'quantity' => $line['quantity'],
                    'unit_id' => $variant->item->stock_unit_id,
                    'unit_price' => $line['unit_price'],
                    'tax_rate' => ($line['tax_rate'] ?? '') === '' ? null : $line['tax_rate'],
                    'line_total' => $lineTotal,
                    'due_date' => ($line['due_date'] ?? null) ?: null,
                    'sort_order' => $i,
                ])->save();
                $keep[] = $model->id;
            }

            foreach ($existing->except($keep) as $removed) {
                if ($this->hasActivity($removed)) {
                    throw new DocumentException("A line for {$removed->variant->sku} has stock activity and cannot be deleted. Close it instead.");
                }
                $removed->delete();
            }

            $order->forceFill(['total' => $total])->save();

            return $order;
        });
    }

    public function hasActivity(SalesOrderLine $line): bool
    {
        return Decimal::compare($line->delivered_qty, '0') > 0
            || $line->reservations()->exists()
            || $line->allocations()->exists()
            || StockMovement::query()->where('sales_order_line_id', $line->id)->exists();
    }

    public function cancel(SalesOrder $order, string $reason): void
    {
        if ($order->lines->contains(fn (SalesOrderLine $l) => Decimal::compare($l->delivered_qty, '0') > 0
            || StockMovement::query()->where('sales_order_line_id', $l->id)->exists())) {
            throw new DocumentException("{$order->number} has posted activity. Close the remaining quantity instead.");
        }

        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }

        DB::transaction(function () use ($order, $reason) {
            $this->releaseAll($order, "Order cancelled: {$reason}");

            if (in_array($order->status, ['draft', 'approved'], true)) {
                $this->workflow->cancel($order, $reason);
            } else {
                SalesOrder::query()->lockForUpdate()->findOrFail($order->id)
                    ->forceFill(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
                activity('documents')->performedOn($order)->event('cancelled')->withProperties(['reason' => $reason])->log('Cancelled');
            }
        });

        $order->refresh();
    }

    /**
     * Closes everything not yet delivered.
     */
    public function close(SalesOrder $order, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required to close an order.');
        }

        DB::transaction(function () use ($order, $reason) {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! $locked->isOpen()) {
                throw new DocumentException("{$locked->number} is not open.");
            }

            foreach ($locked->lines()->lockForUpdate()->get() as $line) {
                $line->update(['closed_qty' => Decimal::add($line->closed_qty, $line->remaining(), 4)]);
            }

            $this->releaseAll($locked, "Order closed: {$reason}");
            $locked->forceFill(['status' => 'closed', 'closed_at' => now(), 'close_reason' => $reason])->save();
            activity('documents')->performedOn($locked)->event('closed')->withProperties(['reason' => $reason])->log('Closed');
        });

        $order->refresh();
    }

    /**
     * Status from progress: delivered, partly delivered, in planning or approved.
     * Production modules may report in_progress through $hasProduction.
     */
    public function refreshStatus(SalesOrder $order): void
    {
        if (! $order->isOpen()) {
            return;
        }

        $lines = $order->lines()->get();
        $delivered = $lines->every(fn (SalesOrderLine $l) => Decimal::isZero($l->remaining()));
        $anyDelivered = $lines->contains(fn (SalesOrderLine $l) => Decimal::compare($l->delivered_qty, '0') > 0);
        $planned = $lines->contains(fn (SalesOrderLine $l) => $l->reservations()->exists() || $l->allocations()->exists()
            || Decimal::compare(Decimal::add(Decimal::add($l->plan_buy, $l->plan_make, 4), $l->plan_contractor, 4), '0') > 0);
        $inProduction = $lines->contains(fn (SalesOrderLine $l) => $l->allocations()->where('supply_type', '!=', (new PurchaseOrderLine)->getMorphClass())->exists());

        $status = match (true) {
            $delivered => 'delivered',
            $anyDelivered => 'partly_delivered',
            $inProduction => 'in_progress',
            $planned => 'in_planning',
            default => 'approved',
        };

        $order->forceFill(['status' => $status])->save();
    }

    private function releaseAll(SalesOrder $order, string $reason): void
    {
        foreach ($order->lines as $line) {
            foreach ($line->reservations()->where('status', Reservation::ACTIVE)->get() as $reservation) {
                $this->stock->release($reservation, $reason);
            }
            SupplyAllocation::query()->where('sales_order_line_id', $line->id)->where('status', 'open')->update(['status' => 'closed']);
        }
    }
}
