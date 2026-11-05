<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Physical count: lists system quantities for a location, takes the counted
 * quantities, and turns the differences into a draft stock adjustment.
 */
class CountSheet
{
    public function __construct(private readonly DocumentNumber $numbers) {}

    /**
     * @return Collection<int, StockBalance>
     */
    public function rows(int $locationId, ?string $itemType = null): Collection
    {
        return StockBalance::query()
            ->with(['variant.item', 'stage', 'lot'])
            ->where('location_id', $locationId)
            ->where('status', StockStatus::AVAILABLE)
            ->where('quantity', '>', 0)
            ->when($itemType, fn ($q) => $q->whereHas('variant.item', fn ($i) => $i->where('type', $itemType)))
            ->orderBy('item_variant_id')->orderBy('stage_id')->orderBy('lot_id')
            ->get();
    }

    /**
     * @param  array<int, string|null>  $counted  balance id => counted quantity (NULL/'' = not counted)
     */
    public function toAdjustment(int $locationId, array $counted, CarbonInterface $date, ?int $reasonId, ?string $notes = null): ?StockAdjustment
    {
        return DB::transaction(function () use ($locationId, $counted, $date, $reasonId, $notes) {
            $balances = StockBalance::query()->where('location_id', $locationId)->whereIn('id', array_keys($counted))->get()->keyBy('id');
            $lines = [];

            foreach ($counted as $balanceId => $quantity) {
                if ($quantity === null || $quantity === '' || ! isset($balances[$balanceId])) {
                    continue;
                }

                $balance = $balances[$balanceId];
                $difference = Decimal::sub($quantity, $balance->quantity, Decimal::QUANTITY);

                if (Decimal::isZero($difference)) {
                    continue;
                }

                $lines[] = [
                    'item_variant_id' => $balance->item_variant_id,
                    'stage_id' => $balance->stage_id,
                    'status' => $balance->status,
                    'location_id' => $balance->location_id,
                    'lot_id' => $balance->lot_id,
                    'quantity' => $difference,
                    'counted_quantity' => Decimal::round($quantity, Decimal::QUANTITY),
                    'system_quantity' => $balance->quantity,
                ];
            }

            if ($lines === []) {
                return null;
            }

            $adjustment = StockAdjustment::query()->create([
                'number' => $this->numbers->next('stock_adjustment', $date),
                'adjustment_date' => $date->format('Y-m-d'),
                'reason_id' => $reasonId,
                'notes' => $notes,
                'from_count' => true,
                'created_by' => auth()->id(),
            ]);
            $adjustment->lines()->createMany($lines);

            return $adjustment;
        });
    }
}
