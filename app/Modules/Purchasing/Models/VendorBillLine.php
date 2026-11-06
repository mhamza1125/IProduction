<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\ItemVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vendor_bill_id
 * @property int $goods_receipt_line_id
 * @property int $item_variant_id
 * @property string $quantity
 * @property string $rate
 * @property string $amount
 */
class VendorBillLine extends Model
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
}
