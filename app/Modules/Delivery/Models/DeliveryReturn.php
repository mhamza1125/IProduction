<?php

namespace App\Modules\Delivery\Models;

use App\Modules\Inventory\Models\Lot;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Goods returned by the customer: back in Returned status for inspection.
 *
 * @property int $id
 * @property int $customer_id
 * @property int $delivery_line_id
 * @property int|null $invoice_line_id
 * @property int $lot_id
 * @property string $quantity
 * @property string $reason
 * @property Carbon $return_date
 * @property bool $replacement_required
 * @property string|null $notes
 * @property string|null $unit_cost
 * @property string $restocked_qty
 * @property string $rejected_qty
 * @property-read Customer $customer
 * @property-read DeliveryLine $deliveryLine
 * @property-read Lot $lot
 */
class DeliveryReturn extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $attributes = ['restocked_qty' => '0', 'rejected_qty' => '0', 'replacement_required' => false];

    protected $fillable = ['number', 'customer_id', 'delivery_line_id', 'invoice_line_id', 'lot_id', 'quantity', 'reason', 'return_date', 'replacement_required', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'restocked_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4', 'replacement_required' => 'boolean', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['unit_cost', 'restocked_qty', 'rejected_qty'];
    }

    public function documentType(): string
    {
        return 'delivery_return';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->return_date;
    }

    public function permissionKey(): string
    {
        return 'delivery_returns';
    }

    public function showUrl(): string
    {
        return route('delivery.returns.show', $this);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<DeliveryLine, $this> */
    public function deliveryLine(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class);
    }

    /** @return BelongsTo<InvoiceLine, $this> */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** Returned pieces not yet restocked or sent to rejections. */
    public function uninspected(): string
    {
        return Decimal::sub($this->quantity, Decimal::add($this->restocked_qty, $this->rejected_qty, 4), 4);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'quantity', 'reason', 'restocked_qty', 'rejected_qty'])->logOnlyDirty()->useLogName('documents');
    }
}
