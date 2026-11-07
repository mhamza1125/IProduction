<?php

namespace App\Modules\Sales\Services;

use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Models\SupplyAllocation;
use App\Support\Decimal;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Model;

/**
 * How other modules (production) link their supply to a sales order line.
 * Planning counts open links as incoming, never as stock.
 */
class SupplyLinks
{
    public function __construct(private readonly StockLedger $stock, private readonly SalesOrders $orders) {}

    public function link(SalesOrderLine $line, Model $supply, int $variantId, string $quantity): SupplyAllocation
    {
        $allocation = SupplyAllocation::query()->create([
            'sales_order_line_id' => $line->id,
            'supply_type' => $supply->getMorphClass(),
            'supply_id' => $supply->getKey(),
            'item_variant_id' => $variantId,
            'quantity' => $quantity,
            'created_by' => auth()->id(),
        ]);
        $this->orders->refreshStatus($line->order);

        return $allocation;
    }

    /**
     * Supply arrived: fulfils open links and reserves finished goods (no
     * stage) to the order line. Returns the quantity reserved.
     */
    public function fulfil(Model $supply, string $quantity, int $lotId, ?int $stageId): string
    {
        $arrived = Decimal::of($quantity);
        $reserved = BigDecimal::zero();

        $allocations = SupplyAllocation::query()->with('orderLine.order')
            ->where('supply_type', $supply->getMorphClass())->where('supply_id', $supply->getKey())
            ->where('status', 'open')->orderBy('id')->lockForUpdate()->get();

        foreach ($allocations as $allocation) {
            if (! $arrived->isPositive()) {
                break;
            }
            $take = BigDecimal::min(Decimal::of($allocation->open()), $arrived);
            $qty = (string) $take->toScale(4);

            if ($allocation->item_variant_id === $allocation->orderLine->item_variant_id && $stageId === null && $allocation->orderLine->order->isOpen()) {
                $this->stock->reserve($allocation->orderLine, $allocation->item_variant_id, $qty, null, $lotId);
                $reserved = $reserved->plus($take);
            }

            $fulfilled = Decimal::add($allocation->fulfilled_quantity, $qty, 4);
            $allocation->update(['fulfilled_quantity' => $fulfilled, 'status' => Decimal::compare($fulfilled, $allocation->quantity) >= 0 ? 'fulfilled' : 'open']);
            $arrived = $arrived->minus($take);
        }

        return (string) $reserved->toScale(4);
    }

    /** Moves open linked quantity from one supply to another (split / merge). */
    public function move(Model $from, Model $to, string $quantity): void
    {
        $left = Decimal::of($quantity);
        $rows = SupplyAllocation::query()->where('supply_type', $from->getMorphClass())->where('supply_id', $from->getKey())
            ->where('status', 'open')->orderBy('id')->lockForUpdate()->get();

        foreach ($rows as $row) {
            if (! $left->isPositive()) {
                break;
            }
            $take = BigDecimal::min(Decimal::of($row->open()), $left);
            $qty = (string) $take->toScale(4);
            $row->update(['quantity' => Decimal::sub($row->quantity, $qty, 4)]);
            if (Decimal::compare($row->fulfilled_quantity, $row->quantity) >= 0) {
                $row->update(['status' => Decimal::isZero($row->quantity) ? 'closed' : 'fulfilled']);
            }
            SupplyAllocation::query()->create([
                'sales_order_line_id' => $row->sales_order_line_id, 'supply_type' => $to->getMorphClass(), 'supply_id' => $to->getKey(),
                'item_variant_id' => $row->item_variant_id, 'quantity' => $qty, 'created_by' => auth()->id(),
            ]);
            $left = $left->minus($take);
        }
    }

    /** Closes whatever is still open (supply finished or stopped). */
    public function close(Model $supply): void
    {
        SupplyAllocation::query()->where('supply_type', $supply->getMorphClass())->where('supply_id', $supply->getKey())
            ->where('status', 'open')->update(['status' => 'closed']);
    }
}
