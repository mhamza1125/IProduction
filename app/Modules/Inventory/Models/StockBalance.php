<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached quantity per variant / stage / status / location / lot.
 * Maintained by StockLedger; rebuildable from stock_movements.
 *
 * @property int $id
 * @property string $bucket
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string $status
 * @property int $location_id
 * @property int $lot_id
 * @property string $quantity
 */
class StockBalance extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    public static function bucketKey(int $variantId, ?int $stageId, string $status, int $locationId, int $lotId): string
    {
        return implode(':', [$variantId, $stageId ?? 0, $status, $locationId, $lotId]);
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class)->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
