<?php

namespace App\Modules\Payroll\Models;

use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property int $employee_id
 * @property Carbon $month
 * @property string|null $working_days
 * @property string|null $days_off
 * @property string|null $paid_leaves
 * @property string|null $unpaid_leaves
 * @property string|null $late_hours
 * @property string|null $overtime_hours
 * @property string|null $notes
 * @property-read Employee $employee
 */
class AttendanceSummary extends Model
{
    use LogsActivity;

    public const FIELDS = ['working_days', 'days_off', 'paid_leaves', 'unpaid_leaves', 'late_hours', 'overtime_hours'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['month' => 'date'] + array_fill_keys(self::FIELDS, 'decimal:2');
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly([...self::FIELDS, 'notes'])->logOnlyDirty()->useLogName('payroll');
    }
}
