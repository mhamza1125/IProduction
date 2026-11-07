<?php

namespace App\Modules\Finance\Models;

use App\Modules\People\Models\Customer;
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
 * Money received from a customer, in the customer's currency. Amount cleared = deductions + net received.
 *
 * @property int $id
 * @property Carbon $receipt_date
 * @property int $customer_id
 * @property string $currency_code
 * @property string $amount
 * @property int $money_account_id
 * @property string $net_received
 * @property string|null $reference
 * @property string|null $notes
 * @property list<array{type: string, id: int, amount: string}>|null $planned_allocations
 * @property string $allocated_amount
 * @property-read Customer $customer
 * @property-read MoneyAccount $moneyAccount
 */
class Receipt extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'receipt_date', 'customer_id', 'currency_code', 'amount', 'money_account_id', 'net_received', 'reference', 'notes', 'planned_allocations', 'created_by'];

    protected function casts(): array
    {
        return ['receipt_date' => 'date', 'amount' => 'decimal:2', 'net_received' => 'decimal:2', 'allocated_amount' => 'decimal:2', 'planned_allocations' => 'array', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['allocated_amount'];
    }

    public function documentType(): string
    {
        return 'receipt';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->receipt_date;
    }

    public function permissionKey(): string
    {
        return 'receipts';
    }

    public function showUrl(): string
    {
        return route('finance.receipts.show', $this);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'money_account_id');
    }

    /** @return HasMany<ReceiptDeduction, $this> */
    public function deductions(): HasMany
    {
        return $this->hasMany(ReceiptDeduction::class);
    }

    /** @return HasMany<ReceiptAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(ReceiptAllocation::class);
    }

    /** Part of the receipt not yet set against an invoice (a customer advance). */
    public function unallocated(): string
    {
        return Decimal::sub($this->amount, $this->allocated_amount);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount', 'net_received', 'allocated_amount'])->logOnlyDirty()->useLogName('documents');
    }
}
