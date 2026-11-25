<?php

namespace App\Modules\Purchasing\Models;

use App\Modules\Finance\Models\MoneyAccount;
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
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Additional purchase charges (freight, delivery, customs duty, clearing,
 * insurance...) added to the cost of the goods received on chosen receipts,
 * instead of being expensed. Paid now from cash or bank, or payable to a vendor
 * (the transporter or agent, who need not be the goods' vendor). PKR only.
 *
 * @property int $id
 * @property Carbon $cost_date
 * @property int|null $vendor_id
 * @property int|null $money_account_id
 * @property string $method value | quantity
 * @property string $total
 * @property string $paid_amount
 * @property string|null $reference
 * @property string|null $notes
 * @property int|null $journal_id
 * @property int|null $created_by
 * @property-read Vendor|null $vendor
 * @property-read MoneyAccount|null $moneyAccount
 */
class LandedCost extends Model implements Attachable, Document, Payable
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const CHARGE_TYPES = ['freight' => 'Freight / delivery', 'customs_duty' => 'Customs duty', 'clearing' => 'Clearing and forwarding',
        'insurance' => 'Insurance', 'loading' => 'Loading / unloading', 'other' => 'Other'];

    public const METHODS = ['value' => 'By value', 'quantity' => 'By quantity'];

    protected $fillable = ['number', 'cost_date', 'vendor_id', 'money_account_id', 'method', 'total', 'reference', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['cost_date' => 'date', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['paid_amount', 'journal_id'];
    }

    public function documentType(): string
    {
        return 'landed_cost';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->cost_date;
    }

    public function permissionKey(): string
    {
        return 'landed_costs';
    }

    public function showUrl(): string
    {
        return route('purchasing.landed-costs.show', $this);
    }

    /** @return HasMany<LandedCostCharge, $this> */
    public function charges(): HasMany
    {
        return $this->hasMany(LandedCostCharge::class);
    }

    /** @return BelongsToMany<GoodsReceipt, $this> */
    public function receipts(): BelongsToMany
    {
        return $this->belongsToMany(GoodsReceipt::class, 'landed_cost_receipts');
    }

    /** @return HasMany<LandedCostAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(LandedCostAllocation::class);
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    public function isPayable(): bool
    {
        return $this->money_account_id === null;
    }

    public function payableBalance(): string
    {
        return $this->isPosted() && $this->isPayable() ? Decimal::sub($this->total, $this->paid_amount) : '0.00';
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
        return "Landed cost {$this->number}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total', 'paid_amount', 'method'])->logOnlyDirty()->useLogName('documents');
    }
}
