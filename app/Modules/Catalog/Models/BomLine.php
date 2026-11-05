<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Support\Documents\DocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $bom_id
 * @property int $component_item_id
 * @property int|null $component_variant_id
 * @property string $quantity
 * @property int $unit_id
 * @property string $wastage_percent
 * @property int|null $stage_id
 * @property bool $is_optional
 * @property string|null $notes
 * @property int $sort_order
 */
class BomLine extends Model
{
    protected $fillable = [
        'bom_id', 'component_item_id', 'component_variant_id', 'quantity', 'unit_id', 'wastage_percent', 'stage_id', 'is_optional', 'notes', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'wastage_percent' => 'decimal:2', 'is_optional' => 'boolean'];
    }

    protected static function booted(): void
    {
        $guard = function (BomLine $line) {
            if (Bom::query()->whereKey($line->bom_id)->value('status') !== Bom::DRAFT) {
                throw new DocumentException('Lines of an approved BOM cannot be changed. Create a new version.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<Bom, $this> */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function componentItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'component_item_id')->withTrashed();
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function componentVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'component_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class)->withTrashed();
    }
}
