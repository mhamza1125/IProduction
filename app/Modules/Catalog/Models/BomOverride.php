<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Models\Unit;
use App\Support\Documents\DocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A change to the BOM for some parent variants:
 *  - match_attribute_value_id: every variant with that value (e.g. Size = Large)
 *  - match_variant_id: one exact variant
 * With bom_line_id it changes (or removes) that line; without, it adds a component.
 * Variant-specific overrides win over attribute-value overrides.
 *
 * @property int $id
 * @property int $bom_id
 * @property int|null $match_attribute_value_id
 * @property int|null $match_variant_id
 * @property int|null $bom_line_id
 * @property int|null $component_item_id
 * @property int|null $component_variant_id
 * @property string|null $quantity
 * @property int|null $unit_id
 * @property string|null $wastage_percent
 * @property int|null $stage_id
 * @property bool $removes_line
 * @property string|null $notes
 */
class BomOverride extends Model
{
    protected $fillable = [
        'bom_id', 'match_attribute_value_id', 'match_variant_id', 'bom_line_id', 'component_item_id',
        'component_variant_id', 'quantity', 'unit_id', 'wastage_percent', 'stage_id', 'removes_line', 'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'wastage_percent' => 'decimal:2', 'removes_line' => 'boolean'];
    }

    protected static function booted(): void
    {
        $guard = function (BomOverride $override) {
            if (Bom::query()->whereKey($override->bom_id)->value('status') !== Bom::DRAFT) {
                throw new DocumentException('Overrides of an approved BOM cannot be changed. Create a new version.');
            }
        };

        static::saving($guard);
        static::deleting($guard);
    }

    /** @return BelongsTo<AttributeValue, $this> */
    public function matchAttributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class, 'match_attribute_value_id');
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function matchVariant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'match_variant_id')->withTrashed();
    }

    /** @return BelongsTo<BomLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(BomLine::class, 'bom_line_id');
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
}
