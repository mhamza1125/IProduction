<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\ItemVariant;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Holds quantity for a demand (a sales order line). Reduces free stock but
 * does not move stock. Released with a reason, or consumed by issues/deliveries.
 *
 * @property int $id
 * @property string $demand_type
 * @property int $demand_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property int|null $lot_id
 * @property string $quantity
 * @property string $consumed_quantity
 * @property string $status
 * @property string|null $release_reason
 */
class Reservation extends Model
{
    use LogsActivity;

    public const ACTIVE = 'active';

    public const RELEASED = 'released';

    public const CONSUMED = 'consumed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'consumed_quantity' => 'decimal:4', 'released_at' => 'datetime'];
    }

    /** Quantity still held. */
    public function open(): string
    {
        return Decimal::sub($this->quantity, $this->consumed_quantity, Decimal::QUANTITY);
    }

    /** @param Builder<Reservation> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::ACTIVE);
    }

    /** @return MorphTo<Model, $this> */
    public function demand(): MorphTo
    {
        return $this->morphTo();
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

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['quantity', 'consumed_quantity', 'status', 'lot_id', 'release_reason'])->logOnlyDirty();
    }
}
