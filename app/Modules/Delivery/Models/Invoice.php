<?php

namespace App\Modules\Delivery\Models;

use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Finance\Receivable;
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
 * Makes the customer owe money, in the customer's currency, when posted.
 *
 * @property int $id
 * @property int $customer_id
 * @property string $currency_code
 * @property Carbon $invoice_date
 * @property Carbon|null $due_date
 * @property string|null $payment_terms
 * @property string|null $notes
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $total
 * @property-read Customer $customer
 * @property string|null $incoterm
 * @property string|null $incoterm_place
 * @property string|null $port_of_loading
 * @property string|null $port_of_discharge
 * @property string|null $final_destination
 * @property string|null $vessel_flight
 * @property string|null $container_no
 * @property string|null $bl_awb_no
 * @property Carbon|null $bl_awb_date
 * @property string|null $fi_number
 * @property string|null $gd_number
 * @property Carbon|null $gd_date
 * @property string|null $consignee
 * @property string|null $notify_party
 * @property string|null $shipping_marks
 */
class Invoice extends Model implements Attachable, Document, Receivable
{
    /** Incoterms 2020. */
    public const INCOTERMS = [
        'EXW' => 'EXW · Ex Works', 'FCA' => 'FCA · Free Carrier', 'FAS' => 'FAS · Free Alongside Ship', 'FOB' => 'FOB · Free On Board',
        'CFR' => 'CFR · Cost and Freight', 'CIF' => 'CIF · Cost, Insurance and Freight', 'CPT' => 'CPT · Carriage Paid To',
        'CIP' => 'CIP · Carriage and Insurance Paid To', 'DAP' => 'DAP · Delivered At Place', 'DPU' => 'DPU · Delivered at Place Unloaded',
        'DDP' => 'DDP · Delivered Duty Paid',
    ];

    /** Shipping and customs details: printed on the commercial invoice, editable after posting (no accounting effect). */
    public const SHIPPING_FIELDS = [
        'incoterm', 'incoterm_place', 'port_of_loading', 'port_of_discharge', 'final_destination', 'vessel_flight', 'container_no',
        'bl_awb_no', 'bl_awb_date', 'fi_number', 'gd_number', 'gd_date', 'consignee', 'notify_party', 'shipping_marks',
    ];

    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'customer_id', 'currency_code', 'invoice_date', 'due_date', 'payment_terms', 'notes', 'created_by', ...self::SHIPPING_FIELDS];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date', 'bl_awb_date' => 'date', 'gd_date' => 'date', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'total' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['subtotal', 'discount_total', 'tax_total', 'total', 'due_date', 'notes', ...self::SHIPPING_FIELDS];
    }

    public function documentType(): string
    {
        return 'invoice';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->invoice_date;
    }

    public function permissionKey(): string
    {
        return 'invoices';
    }

    public function showUrl(): string
    {
        return route('delivery.invoices.show', $this);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<InvoiceAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoiceAllocation::class);
    }

    public function receivableBalance(): string
    {
        return $this->isPosted() ? $this->balance() : '0.00';
    }

    public function receivableCurrency(): string
    {
        return $this->currency_code;
    }

    public function applyReceipt(Model $source, string $amount, CarbonInterface $date): void
    {
        InvoiceAllocation::query()->create([
            'invoice_id' => $this->id, 'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(),
            'allocation_date' => $date->format('Y-m-d'), 'amount' => $amount, 'created_by' => auth()->id(),
        ]);
    }

    public function receivableBy(Model $customer): bool
    {
        return $customer instanceof Customer && $customer->id === $this->customer_id;
    }

    public function receivableLabel(): string
    {
        return "Invoice {$this->number}";
    }

    /** Amount still owed on this invoice. */
    public function balance(): string
    {
        return Decimal::sub($this->total, Decimal::sum($this->allocations()->pluck('amount')));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'invoice_date', 'due_date', 'total'])->logOnlyDirty()->useLogName('documents');
    }
}
