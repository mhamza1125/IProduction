<?php

namespace App\Modules\Sales\Models;

use App\Modules\People\Models\Customer;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Statuses: draft, approved, in_planning, in_progress, partly_delivered,
 * delivered, closed, cancelled. Never "posted": deliveries and invoices are.
 *
 * @property int $id
 * @property int $customer_id
 * @property Carbon $order_date
 * @property Carbon|null $due_date
 * @property string $currency_code
 * @property string|null $customer_po_number
 * @property string|null $shipping_address
 * @property string|null $payment_terms
 * @property string|null $notes
 * @property bool $sells_rejected_stock
 * @property string $total
 * @property Carbon|null $closed_at
 * @property string|null $close_reason
 */
class SalesOrder extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const STATUSES = ['draft', 'approved', 'in_planning', 'in_progress', 'partly_delivered', 'delivered', 'closed', 'cancelled'];

    /** Statuses in which the order still needs supply or delivery. */
    public const OPEN = ['approved', 'in_planning', 'in_progress', 'partly_delivered'];

    protected $fillable = ['number', 'customer_id', 'order_date', 'due_date', 'currency_code', 'customer_po_number', 'shipping_address', 'payment_terms', 'notes', 'sells_rejected_stock', 'total', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'due_date' => 'date', 'sells_rejected_stock' => 'boolean', 'total' => 'decimal:2', 'closed_at' => 'datetime',
            'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        // Users with sales_orders.override may edit approved orders (SalesOrders::saveApproved).
        return ['closed_at', 'close_reason', 'total', 'due_date', 'customer_po_number', 'shipping_address', 'payment_terms', 'notes'];
    }

    public function documentType(): string
    {
        return 'sales_order';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->order_date;
    }

    public function permissionKey(): string
    {
        return 'sales_orders';
    }

    public function showUrl(): string
    {
        return route('sales.orders.show', $this);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /** @return HasMany<SalesOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(SalesOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['customer_id', 'order_date', 'due_date', 'customer_po_number', 'shipping_address', 'payment_terms', 'notes', 'total'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
