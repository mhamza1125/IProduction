<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_loan_id
 * @property int $payroll_line_id
 * @property Carbon $month
 * @property string $amount
 */
class LoanInstallment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2'];
    }
}
