<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Vendor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A traceable stock batch. Every inbound source creates a lot; its cost moves with its stock.
 *
 * @property int $id
 * @property string $lot_number
 * @property int $item_variant_id
 * @property string $origin
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $vendor_id
 * @property int|null $contractor_id
 * @property Carbon $received_date
 * @property string|null $unit_cost
 * @property string|null $notes
 */
class Lot extends Model
{
    public const ORIGINS = [
        'purchased' => 'Purchased',
        'made' => 'Made',
        'contractor_made' => 'Contractor Made',
        'customer_return' => 'Customer Return',
        'rejection_recovery' => 'Rejection Recovery',
        'opening' => 'Opening Stock',
        'adjustment' => 'Adjustment',
    ];

    protected $fillable = ['lot_number', 'item_variant_id', 'origin', 'source_type', 'source_id', 'vendor_id', 'contractor_id', 'received_date', 'unit_cost', 'notes'];

    protected function casts(): array
    {
        return ['received_date' => 'date', 'unit_cost' => 'decimal:4'];
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class)->withTrashed();
    }

    /** @return HasMany<StockMovement, $this> */
    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Lots this lot was made from.
     *
     * @return HasMany<LotLink, $this>
     */
    public function inputs(): HasMany
    {
        return $this->hasMany(LotLink::class, 'lot_id');
    }

    /**
     * Lots made from this lot.
     *
     * @return HasMany<LotLink, $this>
     */
    public function outputs(): HasMany
    {
        return $this->hasMany(LotLink::class, 'source_lot_id');
    }
}
