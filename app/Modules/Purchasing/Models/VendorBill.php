<?php

namespace App\Modules\Purchasing\Models;

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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * The vendor's invoice for accepted receipts. Statuses after posting:
 * posted, partly_paid, paid.
 *
 * @property int $id
 * @property int $vendor_id
 * @property string|null $vendor_invoice_number
 * @property Carbon $bill_date
 * @property Carbon|null $due_date
 * @property string $goods_amount
 * @property string $tax_amount
 * @property string $charges_amount
 * @property string $deductions_amount
 * @property string $total
 * @property string $paid_amount
 * @property string|null $notes
 */
class VendorBill extends Model implements Attachable, Document, Payable
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'vendor_id', 'vendor_invoice_number', 'bill_date', 'due_date', 'goods_amount', 'tax_amount', 'charges_amount', 'deductions_amount', 'total', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['bill_date' => 'date', 'due_date' => 'date', 'goods_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'charges_amount' => 'decimal:2',
            'deductions_amount' => 'decimal:2', 'total' => 'decimal:2', 'paid_amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['paid_amount'];
    }

    public function documentType(): string
    {
        return 'vendor_bill';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->bill_date;
    }

    public function permissionKey(): string
    {
        return 'vendor_bills';
    }

    public function showUrl(): string
    {
        return route('purchasing.bills.show', $this);
    }

    public function balance(): string
    {
        return Decimal::sub($this->total, $this->paid_amount);
    }

    public function payableBalance(): string
    {
        return $this->posted_at === null ? '0.00' : $this->balance();
    }

    public function applyPayment(string $amount): void
    {
        $paid = Decimal::add($this->paid_amount, $amount);
        $this->forceFill([
            'paid_amount' => $paid,
            'status' => Decimal::compare($paid, $this->total) >= 0 ? 'paid' : 'partly_paid',
        ])->save();
    }

    public function payableBy(Model $payee): bool
    {
        return $payee instanceof Vendor && $payee->id === $this->vendor_id;
    }

    public function payableLabel(): string
    {
        return $this->number.($this->vendor_invoice_number ? ' ('.$this->vendor_invoice_number.')' : '');
    }

    /** @return BelongsTo<Vendor, $this> */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class)->withTrashed();
    }

    /** @return HasMany<VendorBillLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(VendorBillLine::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['vendor_invoice_number', 'bill_date', 'due_date', 'tax_amount', 'charges_amount', 'deductions_amount', 'total'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
