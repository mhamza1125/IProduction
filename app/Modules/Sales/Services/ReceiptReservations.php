<?php

namespace App\Modules\Sales\Services;

use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Sales\Models\SupplyAllocation;
use App\Support\Decimal;
use Brick\Math\BigDecimal;

/**
 * When goods bought for an order arrive, reserve them to that order line
 * (finished goods) and mark component supply as arrived. Runs inside the
 * goods receipt's posting transaction.
 */
class ReceiptReservations
{
    public function __construct(private readonly StockLedger $stock) {}

    public function __invoke(GoodsReceipt $receipt): void
    {
        foreach ($receipt->lines()->with('orderLine')->whereNotNull('lot_id')->get() as $grnLine) {
            $arrived = Decimal::of($grnLine->stock_qty);

            $allocations = SupplyAllocation::query()->with('orderLine.order')
                ->where('supply_type', $grnLine->orderLine->getMorphClass())
                ->where('supply_id', $grnLine->purchase_order_line_id)
                ->where('status', 'open')->orderBy('id')->lockForUpdate()->get();

            foreach ($allocations as $allocation) {
                if (! $arrived->isPositive()) {
                    break;
                }

                $take = BigDecimal::min(Decimal::of($allocation->open()), $arrived);
                $qty = (string) $take->toScale(4);

                // Finished goods for the order line are reserved to it straight away.
                if ($allocation->item_variant_id === $allocation->orderLine->item_variant_id && $grnLine->stage_id === null) {
                    $this->stock->reserve($allocation->orderLine, $allocation->item_variant_id, $qty, null, $grnLine->lot_id);
                }

                $fulfilled = Decimal::add($allocation->fulfilled_quantity, $qty, 4);
                $allocation->update([
                    'fulfilled_quantity' => $fulfilled,
                    'status' => Decimal::compare($fulfilled, $allocation->quantity) >= 0 ? 'fulfilled' : 'open',
                ]);
                $arrived = $arrived->minus($take);
            }
        }
    }
}
