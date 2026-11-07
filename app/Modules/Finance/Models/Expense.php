<?php

namespace App\Modules\Finance\Models;

use App\Modules\People\Models\Vendor;
use App\Support\Decimal;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Finance\Payable;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An expense paid now from a cash/bank account, or recorded as payable to a vendor (then paid with a Payment).
 *
 * @property int $id
 * @property Carbon $expense_date
 * @property int $expense_account_id
 * @property int|null $money_account_id
 * @property int|null $vendor_id
 * @property string $amount
 * @property string $paid_amount
 * @property string|null $reference
 * @property string|null $notes
 * @property-read GlAccount $expenseAccount
 * @property-read MoneyAccount|null $moneyAccount
 * @property-read Vendor|null $vendor
 */
class Expense extends Model implements Attachable, Document, Payable
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'expense_date', 'expense_account_id', 'money_account_id', 'vendor_id', 'amount', 'reference', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['expense_date' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['paid_amount'];
    }

    public function documentType(): string
    {
        return 'expense';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->expense_date;
    }

    public function permissionKey(): string
    {
        return 'expenses';
    }

    public function showUrl(): string
    {
        return route('finance.expenses.show', $this);
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'expense_account_id');
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'money_account_id');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    public function isPayable(): bool
    {
        return $this->money_account_id === null;
    }

    public function payableBalance(): string
    {
        return $this->isPosted() && $this->isPayable() ? Decimal::sub($this->amount, $this->paid_amount) : '0.00';
    }

    public function applyPayment(string $amount): void
    {
        $this->forceFill(['paid_amount' => Decimal::add($this->paid_amount, $amount)])->save();
    }

    public function payableBy(Model $payee): bool
    {
        return $payee instanceof Vendor && $payee->id === $this->vendor_id;
    }

    public function payableLabel(): string
    {
        return "Expense {$this->number}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount', 'paid_amount'])->logOnlyDirty()->useLogName('documents');
    }
}
