<?php

namespace App\Modules\Payroll\Models;

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
 * A month's payroll for all (or filtered) employees: Draft, Approved, Posted, Partly Paid, Paid.
 *
 * @property int $id
 * @property Carbon $month
 * @property int|null $department_id
 * @property string|null $employee_type
 * @property string|null $notes
 * @property string $total_net
 * @property string $total_paid
 */
class PayrollRun extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'month', 'department_id', 'employee_type', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['month' => 'date', 'total_net' => 'decimal:2', 'total_paid' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['total_net', 'total_paid', 'notes'];
    }

    public function documentType(): string
    {
        return 'payroll';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->month;
    }

    public function permissionKey(): string
    {
        return 'payroll';
    }

    public function showUrl(): string
    {
        return route('payroll.runs.show', $this);
    }

    /** @return HasMany<PayrollLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'department_id');
    }

    public function monthLabel(): string
    {
        return $this->month->format('F Y');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total_net', 'total_paid'])->logOnlyDirty()->useLogName('documents');
    }
}
