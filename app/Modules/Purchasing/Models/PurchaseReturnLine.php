<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_return_id
 * @property int $goods_receipt_line_id
 * @property int $item_variant_id
 * @property int $lot_id
 * @property string $status
 * @property int $location_id
 * @property string $quantity
 * @property string $rate
 * @property string $amount
 */
class PurchaseReturnLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'rate' => 'decimal:4', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<GoodsReceiptLine, $this> */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
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
