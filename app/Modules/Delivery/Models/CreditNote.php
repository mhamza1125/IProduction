<?php

namespace App\Modules\Delivery\Models;

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
 * Reduces what the customer owes, in the customer's currency.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $currency_code
 * @property int|null $invoice_id
 * @property int|null $delivery_return_id
 * @property Carbon $credit_date
 * @property string $reason
 * @property string|null $notes
 * @property string $subtotal
 * @property string $tax_total
 * @property string $total
 * @property-read Customer $customer
 * @property-read Invoice|null $invoice
 */
class CreditNote extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'customer_id', 'currency_code', 'invoice_id', 'delivery_return_id', 'credit_date', 'reason', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['credit_date' => 'date', 'subtotal' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['subtotal', 'tax_total', 'total'];
    }

    public function documentType(): string
    {
        return 'credit_note';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->credit_date;
    }

    public function permissionKey(): string
    {
        return 'credit_notes';
    }

    public function showUrl(): string
    {
        return route('delivery.credit-notes.show', $this);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<CreditNoteLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CreditNoteLine::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return BelongsTo<DeliveryReturn, $this> */
    public function deliveryReturn(): BelongsTo
    {
        return $this->belongsTo(DeliveryReturn::class);
    }

    /** Credit not yet set against an invoice (a customer credit / advance). */
    public function unallocated(): string
    {
        $used = Decimal::sum(InvoiceAllocation::query()->where('source_type', $this->getMorphClass())->where('source_id', $this->id)->pluck('amount'));

        return Decimal::sub($this->total, $used);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'reason', 'total'])->logOnlyDirty()->useLogName('documents');
    }
}
