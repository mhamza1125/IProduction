<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\People\Models\Vendor;
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
 * A commitment to buy. Statuses: draft, approved, sent, partly_received,
 * received, closed_short, cancelled. Never posted: stock and the vendor
 * balance change only when goods receipts are posted.
 *
 * @property int $id
 * @property int $vendor_id
 * @property Carbon $order_date
 * @property Carbon|null $expected_date
 * @property string|null $payment_terms
 * @property string|null $notes
 * @property string $total
 * @property Carbon|null $sent_at
 */
class PurchaseOrder extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const STATUSES = ['draft', 'approved', 'sent', 'partly_received', 'received', 'closed_short', 'cancelled'];

    protected $fillable = ['number', 'vendor_id', 'order_date', 'expected_date', 'payment_terms', 'notes', 'total', 'created_by'];

    protected function casts(): array
    {
        return ['order_date' => 'date', 'expected_date' => 'date', 'total' => 'decimal:2', 'sent_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['sent_at'];
    }

    public function documentType(): string
    {
        return 'purchase_order';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->order_date;
    }

    public function permissionKey(): string
    {
        return 'purchase_orders';
    }

    public function showUrl(): string
    {
        return route('purchasing.orders.show', $this);
    }

    /** True while goods can still be received against it. */
    public function isOpen(): bool
    {
        return in_array($this->status, ['approved', 'sent', 'partly_received'], true);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<GoodsReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['vendor_id', 'order_date', 'expected_date', 'payment_terms', 'notes', 'total'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
