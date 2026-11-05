<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ItemFactory;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Unit;
use App\Support\Models\Attachable;
use App\Support\Models\GuardsUsage;
use App\Support\Models\HasAttachmentsAndNotes;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Anything the company buys, makes, stocks or sells. Documents and stock
 * always use a variant; every item has at least one.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int|null $category_id
 * @property string $type
 * @property string|null $description
 * @property int $stock_unit_id
 * @property int|null $purchase_unit_id
 * @property int|null $sales_unit_id
 * @property string|null $default_selling_price
 * @property string|null $default_purchase_cost
 * @property string|null $min_stock
 * @property string|null $tax_rate
 * @property string|null $tax_notes
 * @property string|null $image_path
 * @property bool $can_buy
 * @property bool $can_make
 * @property bool $can_sell
 * @property bool $track_by_lot
 * @property string|null $over_receipt_percent
 * @property bool $is_active
 */
#[UseFactory(ItemFactory::class)]
class Item extends Model implements Attachable
{
    /** @use HasFactory<ItemFactory> */
    use GuardsUsage, HasAttachmentsAndNotes, HasFactory, LogsActivity, SoftDeletes;

    public const RAW_MATERIAL = 'raw_material';

    public const SEMI_FINISHED = 'semi_finished';

    public const FINISHED_GOOD = 'finished_good';

    public const TYPES = [
        self::RAW_MATERIAL => 'Raw Material',
        self::SEMI_FINISHED => 'Semi-Finished',
        self::FINISHED_GOOD => 'Finished Good',
    ];

    protected $fillable = [
        'code', 'name', 'category_id', 'type', 'description', 'stock_unit_id', 'purchase_unit_id', 'sales_unit_id',
        'default_selling_price', 'default_purchase_cost', 'min_stock', 'tax_rate', 'tax_notes', 'image_path',
        'can_buy', 'can_make', 'can_sell', 'track_by_lot', 'over_receipt_percent', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_selling_price' => 'decimal:4',
            'default_purchase_cost' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'tax_rate' => 'decimal:2',
            'over_receipt_percent' => 'decimal:2',
            'can_buy' => 'boolean',
            'can_make' => 'boolean',
            'can_sell' => 'boolean',
            'track_by_lot' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // "Every item has at least one variant, even simple items."
        static::created(function (Item $item) {
            $item->variants()->create(['sku' => $item->code, 'combination_key' => '', 'label' => '']);
        });
    }

    public function permissionKey(): string
    {
        return 'items';
    }

    /** @return array<string, string> */
    public function sensitiveAttributes(): array
    {
        return ['default_purchase_cost' => 'cost.view'];
    }

    /** @return HasMany<ItemVariant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class);
    }

    /** @return BelongsToMany<Attribute, $this> */
    public function attributes(): BelongsToMany
    {
        return $this->belongsToMany(Attribute::class, 'item_attributes')->withPivot('sort_order')->orderByPivot('sort_order');
    }

    /** @return HasMany<ProductionRoute, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(ProductionRoute::class);
    }

    /** @return HasMany<Bom, $this> */
    public function boms(): HasMany
    {
        return $this->hasMany(Bom::class);
    }

    public function activeBom(): ?Bom
    {
        return $this->boms()->where('is_active', true)->first();
    }

    /** @return BelongsTo<Lookup, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'category_id')->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function stockUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'stock_unit_id')->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id')->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function salesUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'sales_unit_id')->withTrashed();
    }

    /** @param Builder<Item> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
