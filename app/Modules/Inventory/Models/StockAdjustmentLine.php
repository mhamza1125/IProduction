<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $stock_adjustment_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string $status
 * @property int $location_id
 * @property int|null $lot_id
 * @property string $quantity
 * @property string|null $unit_cost
 * @property string|null $counted_quantity
 * @property string|null $system_quantity
 * @property string|null $notes
 */
class StockAdjustmentLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'counted_quantity' => 'decimal:4', 'system_quantity' => 'decimal:4'];
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
