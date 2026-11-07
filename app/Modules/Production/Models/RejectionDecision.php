<?php

namespace App\Modules\Production\Models;

use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Rework / scrap / sell / recover charge for (part of) a rejection.
 *
 * @property int $id
 * @property int $rejection_id
 * @property Carbon $decision_date
 * @property string $decision
 * @property string|null $quantity
 * @property int|null $target_ptc_id
 * @property int|null $target_stage_id
 * @property string|null $amount
 * @property string $reason
 * @property int|null $journal_id
 * @property-read Rejection $rejection
 * @property-read Ptc|null $targetPtc
 */
class RejectionDecision extends Model implements Document
{
    use HasDocumentWorkflow, LogsActivity;

    public const DECISIONS = ['rework' => 'Rework', 'scrap' => 'Scrap', 'sell' => 'Sell as rejected', 'recover' => 'Recover charge'];

    protected $fillable = ['number', 'rejection_id', 'decision_date', 'decision', 'quantity', 'target_ptc_id', 'target_stage_id', 'amount', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['decision_date' => 'date', 'quantity' => 'decimal:4', 'amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['journal_id'];
    }

    public function documentType(): string
    {
        return 'rejection_decision';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->decision_date;
    }

    public function permissionKey(): string
    {
        return 'rejections';
    }

    public function showUrl(): string
    {
        return route('production.rejections.show', $this->rejection_id);
    }

    /** @return BelongsTo<Rejection, $this> */
    public function rejection(): BelongsTo
    {
        return $this->belongsTo(Rejection::class);
    }

    /** @return BelongsTo<Ptc, $this> */
    public function targetPtc(): BelongsTo
    {
        return $this->belongsTo(Ptc::class, 'target_ptc_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'decision', 'quantity', 'amount', 'reason'])->logOnlyDirty()->useLogName('documents');
    }
}
