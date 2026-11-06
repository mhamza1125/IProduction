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
 * Goods sent back to a vendor from a received lot.
 *
 * @property int $id
 * @property int $vendor_id
 * @property int $goods_receipt_id
 * @property Carbon $return_date
 * @property string $reason
 * @property string|null $notes
 * @property bool $replacement_required
 * @property int|null $replacement_receipt_id
 * @property string $total
 */
class PurchaseReturn extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'vendor_id', 'goods_receipt_id', 'return_date', 'reason', 'notes', 'replacement_required', 'total', 'created_by'];

    protected function casts(): array
    {
        return ['replacement_required' => 'boolean', 'return_date' => 'date', 'total' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['replacement_receipt_id', 'total'];
    }

    public function documentType(): string
    {
        return 'purchase_return';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->return_date;
    }

    public function permissionKey(): string
    {
        return 'purchase_returns';
    }

    public function showUrl(): string
    {
        return route('purchasing.returns.show', $this);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /** @return BelongsTo<GoodsReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    /** @return HasMany<PurchaseReturnLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseReturnLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['return_date', 'reason', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
