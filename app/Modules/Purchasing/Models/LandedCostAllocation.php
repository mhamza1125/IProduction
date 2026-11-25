<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Inventory\Models\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $landed_cost_id
 * @property int $goods_receipt_line_id
 * @property int $lot_id
 * @property string $basis
 * @property string $lot_qty
 * @property string $on_hand_qty
 * @property string $amount
 * @property string $to_stock
 * @property string $to_production
 * @property string $to_cogs
 * @property string|null $unit_cost_before
 * @property string|null $unit_cost_after
 * @property-read GoodsReceiptLine $receiptLine
 * @property-read Lot $lot
 */
class LandedCostAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['basis' => 'decimal:4', 'lot_qty' => 'decimal:4', 'on_hand_qty' => 'decimal:4', 'amount' => 'decimal:2', 'to_stock' => 'decimal:2',
            'to_production' => 'decimal:2', 'to_cogs' => 'decimal:2', 'unit_cost_before' => 'decimal:4', 'unit_cost_after' => 'decimal:4'];
    }

    /** @return BelongsTo<GoodsReceiptLine, $this> */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class, 'goods_receipt_line_id');
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
