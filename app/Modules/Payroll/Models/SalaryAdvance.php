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
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Part of a salary paid early; payroll deducts it in its salary month, any unused part carries forward.
 *
 * @property int $id
 * @property int $employee_id
 * @property Carbon $advance_date
 * @property string $amount
 * @property Carbon $salary_month
 * @property int $money_account_id
 * @property string $recovered_amount
 * @property string|null $notes
 * @property-read Employee $employee
 * @property-read MoneyAccount $moneyAccount
 */
class SalaryAdvance extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'employee_id', 'advance_date', 'amount', 'salary_month', 'money_account_id', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['advance_date' => 'date', 'salary_month' => 'date', 'amount' => 'decimal:2', 'recovered_amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['recovered_amount'];
    }

    public function documentType(): string
    {
        return 'salary_advance';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->advance_date;
    }

    public function permissionKey(): string
    {
        return 'advances';
    }

    public function showUrl(): string
    {
        return route('payroll.advances.show', $this);
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

    public function balance(): string
    {
        return Decimal::sub($this->amount, $this->recovered_amount);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount', 'recovered_amount'])->logOnlyDirty()->useLogName('documents');
    }
}
