<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of the salary statement: + earning, − deduction.
 *
 * @property int $id
 * @property int $payroll_line_id
 * @property string $kind
 * @property string $description
 * @property string $amount
 * @property string|null $source_type
 * @property int|null $source_id
 */
class PayrollLineItem extends Model
{
    public const KINDS = [
        'salary' => 'Monthly salary', 'wage_work' => 'Wage work', 'overtime' => 'Overtime', 'allowance' => 'Allowance',
        'unpaid_days' => 'Unpaid days', 'late_hours' => 'Late hours', 'attendance_adjusted' => 'Attendance deduction (edited)',
        'fine' => 'Fine', 'loan' => 'Loan installment', 'advance' => 'Advance already paid', 'brought_forward' => 'Brought forward',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
