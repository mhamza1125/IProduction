<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
use App\Support\Finance\Payable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One employee's salary statement in a payroll run. Once posted it is what
 * the employee is owed for the month; payments are allocated to it.
 *
 * @property int $id
 * @property int $payroll_run_id
 * @property int $employee_id
 * @property Carbon $month
 * @property string $employee_type
 * @property string|null $monthly_salary
 * @property string $earnings
 * @property string $overtime
 * @property string $allowances
 * @property string $attendance_deduction
 * @property string $calculated_attendance_deduction
 * @property string|null $deduction_override_reason
 * @property string $fines
 * @property string $loan_installment
 * @property string $advance_deduction
 * @property string $brought_forward
 * @property string $net_payable
 * @property string $carried_forward
 * @property string $paid_amount
 * @property string|null $posted_key
 * @property-read Employee $employee
 * @property-read PayrollRun $run
 */
class PayrollLine extends Model implements Payable
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        $money = array_fill_keys(['monthly_salary', 'earnings', 'overtime', 'allowances', 'attendance_deduction', 'calculated_attendance_deduction', 'fines',
            'loan_installment', 'advance_deduction', 'brought_forward', 'net_payable', 'carried_forward', 'paid_amount'], 'decimal:2');

        return ['month' => 'date'] + $money;
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<PayrollRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    /** @return HasMany<PayrollLineItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PayrollLineItem::class)->orderBy('id');
    }

    public function isPosted(): bool
    {
        return $this->posted_key !== null;
    }

    /** Gross: salary or wage work + overtime + allowances. */
    public function gross(): string
    {
        return Decimal::add(Decimal::add($this->earnings, $this->overtime), $this->allowances);
    }

    public function totalDeductions(): string
    {
        return Decimal::sum([$this->attendance_deduction, $this->fines, $this->loan_installment, $this->advance_deduction, $this->brought_forward]);
    }

    public function payableBalance(): string
    {
        return $this->isPosted() ? Decimal::sub($this->net_payable, $this->paid_amount) : '0.00';
    }

    public function applyPayment(string $amount): void
    {
        $this->forceFill(['paid_amount' => Decimal::add($this->paid_amount, $amount)])->save();
        app(Payrolls::class)->refreshPaid($this->run()->firstOrFail());
    }

    public function payableBy(Model $payee): bool
    {
        return $payee instanceof Employee && $payee->id === $this->employee_id;
    }

    public function payableLabel(): string
    {
        return 'Payroll '.$this->month->format('M Y');
    }
}
