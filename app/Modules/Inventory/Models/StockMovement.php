<?php

namespace App\Modules\Inventory\Models;

use App\Models\User;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One line of the append-only stock ledger. Written only by StockLedger;
 * never updated or deleted. Mistakes are corrected with new movements.
 *
 * @property int $id
 * @property Carbon $movement_date
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string $status
 * @property int $location_id
 * @property int $lot_id
 * @property string $qty_in
 * @property string $qty_out
 * @property string|null $unit_cost
 * @property string|null $total_cost
 * @property string $source_type
 * @property int $source_id
 * @property int|null $source_line_id
 * @property int|null $sales_order_line_id
 * @property string|null $notes
 */
class StockMovement extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['movement_date' => 'date', 'qty_in' => 'decimal:4', 'qty_out' => 'decimal:4', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Stock movements are immutable.'));
        static::deleting(fn () => throw new LogicException('Stock movements are immutable.'));
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

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
