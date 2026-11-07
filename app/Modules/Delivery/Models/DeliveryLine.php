<?php

namespace App\Modules\Delivery\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $delivery_id
 * @property int $sales_order_line_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string $source_status
 * @property string $quantity
 * @property string $returned_qty
 * @property int|null $reservation_id
 * @property string|null $over_reason
 * @property int $sort_order
 * @property-read Delivery $delivery
 * @property-read SalesOrderLine $orderLine
 * @property-read ItemVariant $variant
 */
class DeliveryLine extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['returned_qty' => '0', 'source_status' => 'available'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'returned_qty' => 'decimal:4'];
    }

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return HasMany<DeliveryAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(DeliveryAllocation::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function invoiceLines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }
}
