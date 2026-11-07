<?php

namespace App\Modules\Payroll\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_loan_id
 * @property Carbon $month
 * @property string $amount
 * @property string $reason
 */
class LoanInstallmentChange extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['month' => 'date', 'amount' => 'decimal:2'];
    }
}
