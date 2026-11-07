<?php

namespace App\Modules\Delivery\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Sales\Models\SalesOrderLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $delivery_line_id
 * @property int $sales_order_line_id
 * @property int $item_variant_id
 * @property string|null $description
 * @property string $quantity
 * @property string $unit_price
 * @property string $order_price
 * @property string|null $discount
 * @property string|null $tax_rate
 * @property string $tax_amount
 * @property string $amount
 * @property-read Invoice $invoice
 * @property-read DeliveryLine $deliveryLine
 * @property-read SalesOrderLine $orderLine
 * @property-read ItemVariant $variant
 */
class InvoiceLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:4', 'order_price' => 'decimal:4', 'discount' => 'decimal:2',
            'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<DeliveryLine, $this> */
    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class);
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
}
