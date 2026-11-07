<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryAllocation;
use App\Modules\Delivery\Models\DeliveryLine;
use App\Modules\Delivery\Models\PackingList;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Customer;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\OrderProgress;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deliveries (specification, Module 9): Draft → Allocated → Packed → Dispatched.
 *  - lines come from open order lines of one customer and currency, from any number of orders;
 *  - quantity cannot exceed what is still to deliver unless approved (override + reason);
 *  - stock is reserved to the order line at allocation and leaves at dispatch;
 *  - only Available stock is dispatched, except orders marked for rejected stock;
 *  - packing must be complete before dispatch;
 *  - dispatch posts Dr Cost of Goods Sold, Cr Inventory at lot cost (PKR).
 */
class Deliveries
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
        private readonly JournalPoster $journals,
        private readonly OrderProgress $progress,
    ) {}

    /**
     * Open order lines of a customer with what is still to deliver and what is ready.
     *
     * @return Collection<int, array{line: SalesOrderLine, remaining: string, on_deliveries: string, reserved: string, ready: string}>
     */
    public function openLines(Customer $customer, ?int $exceptDelivery = null): Collection
    {
        return SalesOrderLine::query()->with(['order', 'variant.item'])
            ->whereHas('order', fn ($q) => $q->where('customer_id', $customer->id)->whereIn('status', SalesOrder::OPEN))
            ->get()
            ->map(function (SalesOrderLine $line) use ($exceptDelivery) {
                $pending = $this->pendingOnDeliveries($line, $exceptDelivery);
                $reserved = Decimal::sum(Reservation::query()->where('demand_type', $line->getMorphClass())->where('demand_id', $line->id)->where('status', Reservation::ACTIVE)->get()->map->open(), 4);
                $status = $line->order->sells_rejected_stock ? StockStatus::REJECTED : StockStatus::AVAILABLE;
                $ready = $status === StockStatus::REJECTED ? $this->stock->onHand($line->item_variant_id, null, StockStatus::REJECTED) : Decimal::add($reserved, $this->stock->freeQuantity($line->item_variant_id), 4);

                return ['line' => $line, 'remaining' => Decimal::sub($line->remaining(), $pending, 4), 'on_deliveries' => $pending, 'reserved' => $reserved, 'ready' => $ready];
            })
            ->filter(fn ($r) => Decimal::compare($r['remaining'], '0') > 0)
            ->values();
    }

    /** Quantity of a line on deliveries not yet dispatched or cancelled. */
    public function pendingOnDeliveries(SalesOrderLine $line, ?int $exceptDelivery = null): string
    {
        return Decimal::sum(DeliveryLine::query()->where('sales_order_line_id', $line->id)
            ->whereHas('delivery', fn ($q) => $q->whereIn('status', ['draft', 'allocated', 'packed'])->when($exceptDelivery, fn ($q) => $q->whereKeyNot($exceptDelivery)))
            ->pluck('quantity'), 4);
    }

    /**
     * @param  array<string, mixed>  $header  customer_id, delivery_date, shipping_address, shipment_method, vehicle, tracking_number, notes
     * @param  list<array{sales_order_line_id: int, quantity: string, over_reason?: string|null}>  $lines
     */
    public function save(?Delivery $delivery, array $header, array $lines): Delivery
    {
        if ($delivery && ! $delivery->isDraft()) {
            throw new DocumentException("{$delivery->number} is {$delivery->status} and cannot be edited.");
        }
        $lines = array_values(array_filter($lines, fn ($l) => Decimal::compare((string) $l['quantity'], '0') > 0));
        if ($lines === []) {
            throw new DocumentException('Add at least one line with a quantity.');
        }
        $customer = Customer::query()->findOrFail($header['customer_id']);

        return DB::transaction(function () use ($delivery, $header, $lines, $customer) {
            $orderLines = SalesOrderLine::query()->with('order')->whereIn('id', array_column($lines, 'sales_order_line_id'))->lockForUpdate()->get()->keyBy('id');
            $currency = null;

            foreach ($lines as $i => $l) {
                $line = $orderLines[$l['sales_order_line_id']] ?? throw new DocumentException('Unknown order line.');
                if ($line->order->customer_id !== $customer->id) {
                    throw new DocumentException("{$line->order->number} belongs to another customer. A delivery is for one customer.");
                }
                $currency ??= $line->order->currency_code;
                if ($line->order->currency_code !== $currency) {
                    throw new DocumentException('All lines of a delivery must be in one currency.');
                }
                if (! $line->order->isOpen()) {
                    throw new DocumentException("{$line->order->number} is not open.");
                }
                $left = Decimal::sub($line->remaining(), $this->pendingOnDeliveries($line, $delivery?->id), 4);
                if (Decimal::compare((string) $l['quantity'], $left) > 0) {
                    if (! auth()->user()?->can('deliveries.override') || trim((string) ($l['over_reason'] ?? '')) === '') {
                        throw new DocumentException('Line '.($i + 1).": only {$left} is still to deliver on {$line->order->number}. Delivering more needs approval and a reason.");
                    }
                }
            }

            $delivery ??= new Delivery(['number' => $this->numbers->next('delivery', Carbon::parse($header['delivery_date'])), 'created_by' => auth()->id()]);
            $delivery->fill([
                'customer_id' => $customer->id, 'currency_code' => $currency, 'delivery_date' => $header['delivery_date'],
                'shipping_address' => $header['shipping_address'] ?? null ?: $customer->shipping_address,
                'shipment_method' => $header['shipment_method'] ?? null ?: null, 'vehicle' => $header['vehicle'] ?? null ?: null,
                'tracking_number' => $header['tracking_number'] ?? null ?: null, 'notes' => $header['notes'] ?? null ?: null,
            ])->save();

            $delivery->lines()->delete();
            foreach ($lines as $i => $l) {
                $line = $orderLines[$l['sales_order_line_id']];
                $delivery->lines()->create([
                    'sales_order_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id,
                    'source_status' => $line->order->sells_rejected_stock ? StockStatus::REJECTED : StockStatus::AVAILABLE,
                    'quantity' => $l['quantity'], 'over_reason' => $l['over_reason'] ?? null ?: null, 'sort_order' => $i,
                ]);
            }

            return $delivery;
        });
    }

    /**
     * Allocates stock to every line: the order line's reserved stock first,
     * then free stock (reserved to the order line now). Lots are recorded on
     * the delivery. A shortage on any line rolls the whole delivery back.
     */
    public function allocate(Delivery $delivery): Delivery
    {
        return DB::transaction(function () use ($delivery) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (! $delivery->isDraft()) {
                throw new DocumentException("{$delivery->number} is {$delivery->status}.");
            }

            foreach ($delivery->lines()->with(['orderLine.order', 'variant.item'])->get() as $line) {
                $this->stock->lockVariant($line->item_variant_id);
                $orderLine = $line->orderLine;

                if ($line->source_status === StockStatus::REJECTED) {
                    foreach ($this->stock->allocate($line->item_variant_id, $line->quantity, null, StockStatus::REJECTED) as $a) {
                        $this->recordAllocation($line, $a['bucket'], $a['quantity']);
                    }

                    continue;
                }

                // Reserved for the order line and not already claimed by another open delivery.
                $reserved = Decimal::sum(Reservation::query()->where('demand_type', $orderLine->getMorphClass())->where('demand_id', $orderLine->id)
                    ->where('status', Reservation::ACTIVE)->whereNull('stage_id')->get()->map->open(), 4);
                $claimed = Decimal::sum(DeliveryAllocation::query()->whereHas('line', fn ($q) => $q->where('sales_order_line_id', $orderLine->id)
                    ->whereHas('delivery', fn ($d) => $d->whereIn('status', ['allocated', 'packed'])->whereKeyNot($delivery->id)))->pluck('quantity'), 4);
                $unclaimed = Decimal::sub($reserved, $claimed, 4);
                $extra = Decimal::sub($line->quantity, Decimal::isNegative($unclaimed) ? '0' : $unclaimed, 4);

                if (Decimal::compare($extra, '0') > 0) {
                    $free = $this->stock->freeQuantity($line->item_variant_id);
                    if (Decimal::compare($extra, $free) > 0) {
                        throw new DocumentException("Not enough stock for {$line->variant->fullLabel()} on {$orderLine->order->number}: short by ".Decimal::sub($extra, $free, 4).'.');
                    }
                    $reservation = $this->stock->reserve($orderLine, $line->item_variant_id, $extra);
                    $line->update(['reservation_id' => $reservation->id]);
                }

                foreach ($this->stock->allocate($line->item_variant_id, $line->quantity, null, StockStatus::AVAILABLE, null, $orderLine) as $a) {
                    $this->recordAllocation($line, $a['bucket'], $a['quantity']);
                }
            }

            $delivery->forceFill(['status' => 'allocated'])->save();
            PackingList::query()->firstOrCreate(['delivery_id' => $delivery->id]);
            activity('documents')->performedOn($delivery)->event('allocated')->log('Allocated');

            return $delivery;
        });
    }

    /** Back to draft before dispatch: drops allocations and releases what allocation reserved. */
    public function unallocate(Delivery $delivery, string $reason): Delivery
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }

        return DB::transaction(function () use ($delivery, $reason) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (! in_array($delivery->status, ['allocated', 'packed'], true)) {
                throw new DocumentException("{$delivery->number} is {$delivery->status}.");
            }
            $this->dropAllocations($delivery, "Delivery {$delivery->number} unallocated: {$reason}");
            $delivery->packingList?->cartons()->each(fn ($c) => $c->delete());
            $delivery->packingList?->update(['completed_at' => null]);
            $delivery->forceFill(['status' => 'draft'])->save();
            activity('documents')->performedOn($delivery)->event('unallocated')->withProperties(['reason' => $reason])->log('Back to draft');

            return $delivery;
        });
    }

    public function cancel(Delivery $delivery, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        DB::transaction(function () use ($delivery, $reason) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if (! in_array($delivery->status, ['draft', 'allocated', 'packed'], true)) {
                throw new DocumentException("{$delivery->number} is {$delivery->status} and cannot be cancelled.");
            }
            $this->dropAllocations($delivery, "Delivery {$delivery->number} cancelled: {$reason}");
            $delivery->forceFill(['status' => 'cancelled', 'cancelled_by' => auth()->id(), 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();
            activity('documents')->performedOn($delivery)->event('cancelled')->withProperties(['reason' => $reason])->log('Cancelled');
        });
    }

    /**
     * Dispatch: stock leaves (the order line's reservation is consumed),
     * Dr Cost of Goods Sold, Cr Inventory at lot cost; order lines are
     * marked delivered.
     */
    public function dispatch(Delivery $delivery, ?string $overrideReason = null): Delivery
    {
        return $this->workflow->post($delivery, function (Delivery $d) {
            $packing = $d->packingList()->first();
            if ($packing?->completed_at === null) {
                throw new DocumentException('Complete the packing list before dispatch.');
            }

            $cost = BigDecimal::zero();
            foreach ($d->lines()->with(['orderLine', 'allocations'])->get() as $line) {
                foreach ($line->allocations as $a) {
                    $source = new StockSource($d, $d->delivery_date, lineId: $line->id, salesOrderLineId: $line->sales_order_line_id);
                    if ($a->status === StockStatus::REJECTED) {
                        $movement = $this->stock->remove(new StockBucket($line->item_variant_id, $a->stage_id, $a->status, $a->location_id, $a->lot_id), $a->quantity, $source);
                    } else {
                        $movement = $this->stock->issue($line->item_variant_id, $a->quantity, $source, $a->stage_id, StockStatus::AVAILABLE, $a->location_id, $line->orderLine, $a->lot_id)[0]['movement'];
                    }
                    $unit = $movement->unit_cost ?? Lot::query()->whereKey($a->lot_id)->value('unit_cost') ?? '0';
                    $a->update(['unit_cost' => $unit]);
                    $cost = $cost->plus(Decimal::of($a->quantity)->multipliedBy($unit));
                }
                $this->progress->delivered($line->orderLine, $line->quantity);
            }

            $amount = (string) $cost->toScale(2, RoundingMode::HalfUp);
            if (Decimal::compare($amount, '0') > 0) {
                $this->journals->post((new JournalEntry($d->delivery_date, 'PKR', "{$d->number} dispatched to {$d->customer->name}", $d))
                    ->debit('cogs', $amount)->credit('inventory', $amount));
            }
        }, $overrideReason, 'dispatched', ['packed']);
    }

    private function recordAllocation(DeliveryLine $line, StockBucket $bucket, string $quantity): void
    {
        DeliveryAllocation::query()->create([
            'delivery_line_id' => $line->id, 'lot_id' => $bucket->lotId, 'location_id' => $bucket->locationId,
            'stage_id' => $bucket->stageId, 'status' => $bucket->status, 'quantity' => $quantity,
        ]);
    }

    private function dropAllocations(Delivery $delivery, string $reason): void
    {
        foreach ($delivery->lines()->get() as $line) {
            DeliveryAllocation::query()->where('delivery_line_id', $line->id)->delete();
            if ($line->reservation_id) {
                $reservation = Reservation::query()->find($line->reservation_id);
                if ($reservation && $reservation->status === Reservation::ACTIVE) {
                    $this->stock->release($reservation, $reason);
                }
                $line->update(['reservation_id' => null]);
            }
        }
    }
}
