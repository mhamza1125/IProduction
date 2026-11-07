<?php

namespace App\Modules\Production\Services;

use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcCost;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Support\Decimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * PTC cost (specification, Module 12): output cost = input material + wages +
 * contractor + extra material + allowed loss. Costs collect on the PTC as
 * signed entries; a release moves the share for the released units onto the
 * output lot, so the lot's cost moves with its stock.
 */
class PtcCosting
{
    public function add(Ptc $ptc, string $category, string $amount, ?Model $source = null, ?string $memo = null): void
    {
        if (Decimal::isZero($amount)) {
            return;
        }
        PtcCost::query()->create([
            'ptc_id' => $ptc->id, 'category' => $category, 'amount' => Decimal::round($amount, 2),
            'source_type' => $source?->getMorphClass(), 'source_id' => $source?->getKey(), 'memo' => $memo,
        ]);
    }

    /** Cost not yet released to stock. */
    public function pool(Ptc $ptc): string
    {
        return Decimal::sum(PtcCost::query()->where('ptc_id', $ptc->id)->pluck('amount'));
    }

    /** Everything ever charged to the PTC (excluding moves out). */
    public function total(Ptc $ptc): string
    {
        return Decimal::sum(PtcCost::query()->where('ptc_id', $ptc->id)->whereNotIn('category', ['released', 'transfer_out'])->pluck('amount'));
    }

    /** @return Collection<string, string> category => amount */
    public function byCategory(Ptc $ptc): Collection
    {
        return PtcCost::query()->where('ptc_id', $ptc->id)->get()->groupBy('category')->map(fn ($rows) => Decimal::sum($rows->pluck('amount')));
    }

    /**
     * Good product units still inside the PTC (Available or In Production at
     * one of its stages), i.e. not released and not rejected.
     */
    public function unitsInPipeline(Ptc $ptc): string
    {
        if ($ptc->output_lot_id === null) {
            return '0.0000';
        }

        return Decimal::sum(StockBalance::query()->where('lot_id', $ptc->output_lot_id)->whereNotNull('stage_id')
            ->whereIn('status', [StockStatus::AVAILABLE, StockStatus::IN_PRODUCTION])
            ->whereIn('stage_id', $ptc->stages()->pluck('stage_id'))->pluck('quantity'), 4);
    }

    /**
     * The share of the pool that $quantity units take with them when they
     * leave the PTC ($remaining = good units that stay behind).
     */
    public function shareFor(Ptc $ptc, string $quantity, string $remaining): string
    {
        $pool = $this->pool($ptc);
        $units = Decimal::add($quantity, $remaining, 4);

        if (Decimal::compare($units, '0') <= 0 || Decimal::compare($pool, '0') <= 0) {
            return '0.00';
        }
        if (Decimal::isZero($remaining)) {
            return $pool;
        }

        return Decimal::round(Decimal::of($pool)->multipliedBy($quantity)->dividedBy($units, 10, RoundingMode::HalfUp), 2);
    }

    /**
     * Material still held for the PTC, by variant, lot and location:
     * issued − returned − wasted − consumed.
     *
     * @return Collection<string, array{item_variant_id: int, lot_id: int, location_id: int, quantity: string}>
     */
    public function heldMaterial(Ptc $ptc): Collection
    {
        $signs = ['material_issue' => 1, 'extra_issue' => 1, 'return_to_store' => -1, 'wastage' => -1, 'consume' => -1];

        return PtcTransaction::query()->where('ptc_id', $ptc->id)->whereIn('type', array_keys($signs))
            ->where('item_variant_id', '!=', $ptc->item_variant_id)
            ->get()
            ->groupBy(fn (PtcTransaction $t) => "{$t->item_variant_id}-{$t->lot_id}-{$t->location_id}")
            ->map(function ($rows) use ($signs) {
                $first = $rows->first();
                $qty = '0';
                foreach ($rows as $t) {
                    $qty = $signs[$t->type] > 0 ? Decimal::add($qty, $t->quantity, 4) : Decimal::sub($qty, $t->quantity, 4);
                }

                return ['item_variant_id' => $first->item_variant_id, 'lot_id' => (int) $first->lot_id, 'location_id' => (int) $first->location_id, 'quantity' => $qty];
            })
            ->filter(fn ($r) => Decimal::compare($r['quantity'], '0') > 0);
    }

    /**
     * Material reconciliation per variant: issued = consumed + returned + wastage + held,
     * with expected BOM use for the good units produced.
     *
     * @return Collection<int, array{variant_id: int, expected: string, issued: string, extra: string, consumed: string, returned: string, wastage: string, held: string, variance: string}>
     */
    public function reconciliation(Ptc $ptc): Collection
    {
        $tx = PtcTransaction::query()->where('ptc_id', $ptc->id)->where('item_variant_id', '!=', $ptc->item_variant_id)
            ->whereIn('type', ['material_issue', 'extra_issue', 'return_to_store', 'wastage', 'consume'])->get()->groupBy('item_variant_id');
        $produced = Decimal::add($ptc->released_qty, $this->unitsInPipeline($ptc), 4);
        $materials = $ptc->materials()->get()->keyBy('item_variant_id');

        return $materials->keys()->merge($tx->keys())->unique()->values()->map(function ($variantId) use ($tx, $materials, $produced) {
            $rows = $tx->get($variantId, collect());
            $sum = fn (string $type) => Decimal::sum($rows->where('type', $type)->pluck('quantity'), 4);
            $issued = $sum('material_issue');
            $extra = $sum('extra_issue');
            $consumed = $sum('consume');
            $returned = $sum('return_to_store');
            $wastage = $sum('wastage');
            $held = Decimal::sub(Decimal::add($issued, $extra, 4), Decimal::add(Decimal::add($consumed, $returned, 4), $wastage, 4), 4);
            $expected = isset($materials[$variantId]) ? Decimal::mul($materials[$variantId]->per_unit, $produced, 4) : '0.0000';

            return [
                'variant_id' => (int) $variantId, 'expected' => $expected, 'issued' => $issued, 'extra' => $extra, 'consumed' => $consumed,
                'returned' => $returned, 'wastage' => $wastage, 'held' => $held,
                'variance' => Decimal::sub(Decimal::add($consumed, $wastage, 4), $expected, 4),
            ];
        });
    }

    /**
     * Direct production cost for an order line: every PTC (children included)
     * made for it.
     */
    public function orderLineCost(SalesOrderLine $line): string
    {
        $ids = Ptc::query()->where('sales_order_line_id', $line->id)->pluck('id');

        return Decimal::sum(PtcCost::query()->whereIn('ptc_id', $ids)->whereNotIn('category', ['released', 'transfer_out', 'transfer_in'])->pluck('amount'));
    }
}
