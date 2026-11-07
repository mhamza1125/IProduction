<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $material_process_id
 * @property string $kind input | output | loss
 * @property int|null $item_variant_id
 * @property int|null $lot_id
 * @property int|null $location_id
 * @property string|null $expected_qty
 * @property string $quantity
 * @property string|null $loss_type
 * @property string|null $reason
 * @property string|null $total_cost
 * @property-read ItemVariant|null $variant
 * @property-read Lot|null $lot
 * @property-read Location|null $location
 */
class MaterialProcessLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_qty' => 'decimal:4', 'quantity' => 'decimal:4', 'total_cost' => 'decimal:2'];
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
