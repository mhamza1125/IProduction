<?php

namespace App\Modules\Production\Models;

use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
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
 * Material converted into other material (thaan into cut pieces...).
 *
 * @property int $id
 * @property Carbon $process_date
 * @property string $process_name
 * @property string|null $worker_type
 * @property int|null $employee_id
 * @property int|null $contractor_id
 * @property int|null $sales_order_line_id
 * @property int|null $ptc_id
 * @property string|null $labor_cost
 * @property string|null $recoverable_value
 * @property string|null $input_cost
 * @property string|null $output_unit_cost
 * @property string|null $notes
 * @property-read Employee|null $employee
 * @property-read Contractor|null $contractor
 */
class MaterialProcess extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'process_date', 'process_name', 'worker_type', 'employee_id', 'contractor_id', 'sales_order_line_id', 'ptc_id', 'labor_cost', 'recoverable_value', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['process_date' => 'date', 'labor_cost' => 'decimal:2', 'recoverable_value' => 'decimal:2', 'input_cost' => 'decimal:2',
            'output_unit_cost' => 'decimal:4', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['input_cost', 'output_unit_cost'];
    }

    public function documentType(): string
    {
        return 'material_process';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->process_date;
    }

    public function permissionKey(): string
    {
        return 'material_processes';
    }

    public function showUrl(): string
    {
        return route('production.processes.show', $this);
    }

    /** @return HasMany<MaterialProcessLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(MaterialProcessLine::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class)->withTrashed();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'process_name', 'labor_cost', 'recoverable_value'])->logOnlyDirty()->useLogName('documents');
    }
}
