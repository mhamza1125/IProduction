<?php

namespace App\Modules\Purchasing\Models;

use App\Models\User;
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
 * What actually arrived against a purchase order.
 *
 * @property int $id
 * @property int $purchase_order_id
 * @property int $vendor_id
 * @property Carbon $receipt_date
 * @property string|null $vendor_delivery_note
 * @property int|null $received_by
 * @property string|null $notes
 * @property string $total_value
 */
class GoodsReceipt extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'purchase_order_id', 'vendor_id', 'receipt_date', 'vendor_delivery_note', 'received_by', 'notes', 'total_value', 'created_by'];

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'total_value' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['total_value'];
    }

    public function documentType(): string
    {
        return 'goods_receipt';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->receipt_date;
    }

    public function permissionKey(): string
    {
        return 'goods_receipts';
    }

    public function showUrl(): string
    {
        return route('purchasing.receipts.show', $this);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<GoodsReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['receipt_date', 'vendor_delivery_note', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
