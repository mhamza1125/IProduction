<?php

namespace App\Modules\Inventory\Services;

use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use Illuminate\Support\Facades\DB;

/**
 * Unit cost used to value a stock bucket, so stock value agrees with the
 * Inventory account.
 *
 * A bucket is valued at its lot's unit cost, except pieces still inside a PTC:
 * the PTC's output lot, at one of the PTC's own stages, available or in
 * production, on the production floor or with a contractor. Their cost is still
 * in the PTC (material in production and the cost pool) and moves onto the lot
 * only when they are released, so valuing them at the lot's released average
 * (after a partial release) would count it twice. They are valued at zero.
 *
 * Rejected pieces from a PTC sit in its zero-cost rejects lot (allowed loss).
 *
 * Limitation: if pieces released from one PTC go back onto the floor as part of
 * the same PTC's route (not the normal flow), they read as pipeline pieces.
 */
class StockValuation
{
    /** @var array<string, bool> */
    private static array $pipeline = [];

    /**
     * SQL expression for the unit cost of a balance row. $b, $l and $loc are the
     * aliases of stock_balances, lots and locations in the caller's query.
     */
    public static function unitCostSql(string $b = 'b', string $l = 'l', string $loc = 'loc'): string
    {
        $statuses = "'".StockStatus::AVAILABLE."', '".StockStatus::IN_PRODUCTION."'";
        $types = "'".Location::PRODUCTION."', '".Location::CONTRACTOR."'";

        return "CASE WHEN {$b}.status IN ({$statuses}) AND {$loc}.type IN ({$types}) AND EXISTS ("
            ."SELECT 1 FROM ptcs vp JOIN ptc_stages vps ON vps.ptc_id = vp.id WHERE vp.output_lot_id = {$b}.lot_id AND vps.stage_id = {$b}.stage_id"
            .") THEN 0 ELSE COALESCE({$l}.unit_cost, 0) END";
    }

    /** Unit cost of one balance (NULL when the lot has no cost). */
    public static function unitCost(StockBalance $balance): ?string
    {
        if (self::inPipeline($balance)) {
            return '0.0000';
        }

        return $balance->lot?->unit_cost;
    }

    public static function inPipeline(StockBalance $balance): bool
    {
        if ($balance->stage_id === null || ! in_array($balance->status, [StockStatus::AVAILABLE, StockStatus::IN_PRODUCTION], true)) {
            return false;
        }
        if (! in_array($balance->location?->type, [Location::PRODUCTION, Location::CONTRACTOR], true)) {
            return false;
        }

        // Memoised per request: list exports call this for every bucket.
        return self::$pipeline[$balance->lot_id.':'.$balance->stage_id] ??= DB::table('ptcs')->join('ptc_stages', 'ptc_stages.ptc_id', '=', 'ptcs.id')
            ->where('ptcs.output_lot_id', $balance->lot_id)->where('ptc_stages.stage_id', $balance->stage_id)->exists();
    }
}
