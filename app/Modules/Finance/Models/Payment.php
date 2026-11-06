<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Lookup;
use App\Support\Decimal;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Money paid out of a cash/bank account. Allocated amounts settle bills
 * (or contractor jobs, payrolls); the rest stays as an advance.
 *
 * @property int $id
 * @property Carbon $payment_date
 * @property string $payee_kind
 * @property string|null $payee_type
 * @property int|null $payee_id
 * @property int|null $expense_account_id
 * @property int $money_account_id
 * @property int|null $payment_method_id
 * @property string|null $reference
 * @property string $amount
 * @property string $allocated_amount
 * @property string|null $notes
 * @property list<array{type: string, id: int, amount: string}>|null $planned_allocations
 */
class Payment extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const KINDS = ['vendor' => 'Vendor', 'contractor' => 'Contractor', 'employee' => 'Employee', 'expense' => 'Expense head'];

    protected $fillable = ['number', 'payment_date', 'payee_kind', 'payee_type', 'payee_id', 'expense_account_id', 'money_account_id', 'payment_method_id', 'reference', 'amount', 'notes', 'planned_allocations', 'created_by'];

    protected function casts(): array
    {
        return ['planned_allocations' => 'array', 'payment_date' => 'date', 'amount' => 'decimal:2', 'allocated_amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['allocated_amount'];
    }

    public function documentType(): string
    {
        return 'payment';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->payment_date;
    }

    public function permissionKey(): string
    {
        return 'payments';
    }

    public function showUrl(): string
    {
        return route('finance.payments.show', $this);
    }

    public function unallocated(): string
    {
        return Decimal::sub($this->amount, $this->allocated_amount);
    }

    /** @return MorphTo<Model, $this> */
    public function payee(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'expense_account_id')->withTrashed();
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    /** @return BelongsTo<Lookup, $this> */
    public function method(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id')->withTrashed();
    }

    /** @return HasMany<PaymentAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['payment_date', 'payee_id', 'money_account_id', 'amount', 'reference', 'notes'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
