<?php

namespace App\Modules\Production\Models;

use App\Modules\People\Models\Contractor;
use App\Modules\Sales\Models\SalesOrderLine;
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
 * A job given to an external contractor (specification, Module 8).
 *
 * @property int $id
 * @property int $contractor_id
 * @property int|null $sales_order_line_id
 * @property Carbon $job_date
 * @property Carbon|null $due_date
 * @property string $payment_basis
 * @property string|null $notes
 * @property string|null $close_reason
 * @property Carbon|null $closed_at
 * @property-read Contractor $contractor
 * @property-read SalesOrderLine|null $orderLine
 */
class ContractorJob extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const STATUSES = ['draft', 'approved', 'material_issued', 'in_progress', 'partly_received', 'received', 'closed', 'cancelled'];

    public const OPEN = ['approved', 'material_issued', 'in_progress', 'partly_received', 'received'];

    protected $fillable = ['number', 'contractor_id', 'sales_order_line_id', 'job_date', 'due_date', 'payment_basis', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['job_date' => 'date', 'due_date' => 'date', 'closed_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['close_reason', 'closed_at', 'due_date', 'notes'];
    }

    public function documentType(): string
    {
        return 'contractor_job';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->job_date;
    }

    public function permissionKey(): string
    {
        return 'contractor_jobs';
    }

    public function showUrl(): string
    {
        return route('production.jobs.show', $this);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class)->withTrashed();
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    /** @return HasMany<ContractorJobLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(ContractorJobLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<ContractorJobMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(ContractorJobMaterial::class);
    }

    /** @return HasMany<ContractorJobTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(ContractorJobTransaction::class)->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'due_date', 'close_reason'])->logOnlyDirty()->useLogName('documents');
    }
}
