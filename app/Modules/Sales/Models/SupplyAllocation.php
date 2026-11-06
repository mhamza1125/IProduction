<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $sales_order_line_id
 * @property string $supply_type
 * @property int $supply_id
 * @property int $item_variant_id
 * @property string $quantity
 * @property string $fulfilled_quantity
 * @property string $status
 */
class SupplyAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'fulfilled_quantity' => 'decimal:4'];
    }

    public function open(): string
    {
        $left = Decimal::sub($this->quantity, $this->fulfilled_quantity, 4);

        return Decimal::isNegative($left) ? '0.0000' : $left;
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    /** @return MorphTo<Model, $this> */
    public function supply(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }
}
