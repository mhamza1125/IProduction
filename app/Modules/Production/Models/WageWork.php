<?php

namespace App\Modules\Production\Models;

use App\Modules\Core\Models\Stage;
use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Pieces accepted from a wage employee at a stage, at the route rate.
 * Rejected units are unpaid unless approved (paid_rejected_qty).
 *
 * @property int $id
 * @property int $employee_id
 * @property int|null $ptc_id
 * @property int|null $ptc_stage_id
 * @property int|null $stage_id
 * @property Carbon $work_date
 * @property string $quantity
 * @property string $rejected_qty
 * @property string $paid_rejected_qty
 * @property string $rate
 * @property string $amount
 * @property string $status
 * @property int|null $journal_id
 * @property int|null $payroll_line_id
 * @property string|null $description
 * @property-read Employee $employee
 * @property-read Ptc|null $ptc
 * @property-read Stage|null $stage
 */
class WageWork extends Model
{
    use LogsActivity;

    protected $table = 'wage_work';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'quantity' => 'decimal:4', 'rejected_qty' => 'decimal:4', 'paid_rejected_qty' => 'decimal:4',
            'rate' => 'decimal:4', 'amount' => 'decimal:2', 'approved_at' => 'datetime'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Ptc, $this> */
    public function ptc(): BelongsTo
    {
        return $this->belongsTo(Ptc::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'quantity', 'paid_rejected_qty', 'amount'])->logOnlyDirty()->useLogName('documents');
    }
}
