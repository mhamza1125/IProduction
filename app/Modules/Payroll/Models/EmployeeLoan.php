<?php

namespace App\Modules\Payroll\Models;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
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
 * Money lent to an employee, recovered by monthly installments through payroll.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $loan_date
 * @property string $amount
 * @property int $money_account_id
 * @property string $installment
 * @property Carbon $start_month
 * @property string $recovered_amount
 * @property string|null $notes
 * @property-read Employee $employee
 * @property-read MoneyAccount $moneyAccount
 */
class EmployeeLoan extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'employee_id', 'loan_date', 'amount', 'money_account_id', 'installment', 'start_month', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['loan_date' => 'date', 'start_month' => 'date', 'amount' => 'decimal:2', 'installment' => 'decimal:2', 'recovered_amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['recovered_amount'];
    }

    public function documentType(): string
    {
        return 'employee_loan';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->loan_date;
    }

    public function permissionKey(): string
    {
        return 'loans';
    }

    public function showUrl(): string
    {
        return route('payroll.loans.show', $this);
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    /** @return HasMany<LoanInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(LoanInstallment::class);
    }

    /** @return HasMany<LoanInstallmentChange, $this> */
    public function changes(): HasMany
    {
        return $this->hasMany(LoanInstallmentChange::class);
    }

    public function balance(): string
    {
        return Decimal::sub($this->amount, $this->recovered_amount);
    }

    /** The installment due for a month (a change for that month wins), never above the balance. */
    public function dueFor(CarbonInterface $month): string
    {
        if (! $this->isPosted() || $month->copy()->startOfMonth()->lt($this->start_month->copy()->startOfMonth())) {
            return '0.00';
        }
        $change = $this->changes()->whereDate('month', $month->copy()->startOfMonth())->value('amount');
        $due = $change ?? $this->installment;
        $balance = $this->balance();

        return Decimal::compare($due, $balance) > 0 ? $balance : Decimal::round((string) $due);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount', 'installment', 'recovered_amount'])->logOnlyDirty()->useLogName('documents');
    }
}
