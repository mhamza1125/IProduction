<?php

namespace App\Modules\Catalog\Models;

use App\Support\Models\GuardsUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The exact version of an item (Glove, Red, Medium). Stock and documents use variants.
 * A used variant cannot be deleted, only deactivated.
 *
 * @property int $id
 * @property int $item_id
 * @property string $sku
 * @property string|null $barcode
 * @property string $combination_key
 * @property string $label
 * @property string|null $price_override
 * @property string|null $cost_override
 * @property string|null $min_stock
 * @property string|null $image_path
 * @property bool $is_active
 */
class ItemVariant extends Model
{
    use GuardsUsage, LogsActivity, SoftDeletes;

    protected $fillable = [
        'item_id', 'sku', 'barcode', 'combination_key', 'label', 'price_override', 'cost_override', 'min_stock', 'image_path', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_override' => 'decimal:4',
            'cost_override' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return BelongsToMany<AttributeValue, $this> */
    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'variant_attribute_values');
    }

    /** @param Builder<ItemVariant> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereHas('item', fn ($q) => $q->where('is_active', true));
    }

    /** "GLV-001 · Leather Glove · Red / M" */
    public function fullLabel(): string
    {
        $item = $this->item;

        return trim($this->sku.' · '.$item->name.($this->label !== '' ? ' · '.$this->label : ''));
    }

    /** Selling price: variant override, else the item default. NULL when neither is set. */
    public function sellingPrice(): ?string
    {
        return $this->price_override ?? $this->item->default_selling_price;
    }

    public function purchaseCost(): ?string
    {
        return $this->cost_override ?? $this->item->default_purchase_cost;
    }

    /** @return array<string, string> */
    public function sensitiveAttributes(): array
    {
        return ['cost_override' => 'cost.view'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
