<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $purchase_order_line_id
 * @property int $item_variant_id
 * @property string $ordered_qty
 * @property string $previously_accepted_qty
 * @property string $received_qty
 * @property string $accepted_qty
 * @property string $rejected_qty
 * @property string $rejected_action
 * @property int|null $reject_reason_id
 * @property string|null $over_receipt_action
 * @property string $extra_qty
 * @property string $stock_qty
 * @property string $payable_qty
 * @property string $rate
 * @property string|null $unit_cost
 * @property string $value
 * @property int $location_id
 * @property int|null $stage_id
 * @property int|null $lot_id
 * @property string $billed_qty
 * @property string $returned_qty
 */
class GoodsReceiptLine extends Model
{
    public const OVER_RECEIPT_ACTIONS = [
        'accept_pay' => 'Accept and pay',
        'accept_free' => 'Accept as free',
        'return_extra' => 'Return extra',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $q = 'decimal:4';

        return [
            'ordered_qty' => $q, 'previously_accepted_qty' => $q, 'received_qty' => $q, 'accepted_qty' => $q, 'rejected_qty' => $q,
            'extra_qty' => $q, 'stock_qty' => $q, 'payable_qty' => $q, 'rate' => $q, 'unit_cost' => $q, 'value' => 'decimal:2',
            'billed_qty' => $q, 'returned_qty' => $q,
        ];
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /** @return BelongsTo<PurchaseOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'purchase_order_line_id');
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class)->withTrashed();
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function rejectReason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'reject_reason_id')->withTrashed();
    }
}
