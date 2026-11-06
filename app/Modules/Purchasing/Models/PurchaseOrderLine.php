<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Quantities are in the order unit; unit_factor converts them to stock units.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string|null $description
 * @property string $quantity
 * @property int $unit_id
 * @property string $unit_factor
 * @property string $rate
 * @property string $amount
 * @property Carbon|null $expected_date
 * @property string $received_qty
 * @property string $accepted_qty
 * @property string $rejected_qty
 * @property string $returned_qty
 * @property string $billed_qty
 * @property string $closed_short_qty
 * @property string|null $close_reason
 */
class PurchaseOrderLine extends Model
{
    /** Columns later documents may update after the order is approved. */
    public const PROGRESS = ['received_qty', 'accepted_qty', 'rejected_qty', 'returned_qty', 'billed_qty', 'closed_short_qty', 'close_reason', 'updated_at'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4', 'unit_factor' => 'decimal:6', 'rate' => 'decimal:4', 'amount' => 'decimal:2', 'expected_date' => 'date',
            'received_qty' => 'decimal:4', 'accepted_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4',
            'returned_qty' => 'decimal:4', 'billed_qty' => 'decimal:4', 'closed_short_qty' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (PurchaseOrderLine $line) {
            $status = PurchaseOrder::query()->whereKey($line->purchase_order_id)->value('status');
            if ($status !== 'draft' && array_diff(array_keys($line->getDirty()), self::PROGRESS) !== []) {
                throw new DocumentException('Lines of an approved purchase order cannot be edited.');
            }
        });
    }

    /** Still to receive: ordered − accepted − closed short (order units, never below zero). */
    public function outstanding(): string
    {
        $left = Decimal::sub(Decimal::sub($this->quantity, $this->accepted_qty, Decimal::QUANTITY), $this->closed_short_qty, Decimal::QUANTITY);

        return Decimal::isNegative($left) ? '0.0000' : $left;
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
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

    /** @return HasMany<GoodsReceiptLine, $this> */
    public function receiptLines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
