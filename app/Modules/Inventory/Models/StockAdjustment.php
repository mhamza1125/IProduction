<?php

namespace App\Modules\Inventory\Models;

use App\Modules\Core\Models\Lookup;
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
 * @property int $id
 * @property Carbon $adjustment_date
 * @property int|null $reason_id
 * @property string|null $notes
 * @property bool $from_count
 */
class StockAdjustment extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'adjustment_date', 'reason_id', 'notes', 'from_count', 'created_by'];

    protected function casts(): array
    {
        return ['adjustment_date' => 'date', 'from_count' => 'boolean', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function documentType(): string
    {
        return 'stock_adjustment';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->adjustment_date;
    }

    public function permissionKey(): string
    {
        return 'stock_adjustments';
    }

    /** @return HasMany<StockAdjustmentLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockAdjustmentLine::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function reason(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'reason_id')->withTrashed();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['adjustment_date', 'reason_id', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
