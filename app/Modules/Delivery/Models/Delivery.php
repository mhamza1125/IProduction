<?php

namespace App\Modules\Delivery\Models;

use App\Modules\People\Models\Customer;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Goods dispatched to one customer, possibly from several orders (specification, Module 9).
 *
 * @property int $id
 * @property int $customer_id
 * @property string $currency_code
 * @property Carbon $delivery_date
 * @property string|null $shipping_address
 * @property string|null $shipment_method
 * @property string|null $vehicle
 * @property string|null $tracking_number
 * @property string|null $notes
 * @property-read Customer $customer
 * @property-read PackingList|null $packingList
 */
class Delivery extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const STATUSES = ['draft', 'allocated', 'packed', 'dispatched', 'cancelled'];

    protected $fillable = ['number', 'customer_id', 'currency_code', 'delivery_date', 'shipping_address', 'shipment_method', 'vehicle', 'tracking_number', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['shipment_method', 'vehicle', 'tracking_number', 'notes'];
    }

    public function documentType(): string
    {
        return 'delivery';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->delivery_date;
    }

    public function permissionKey(): string
    {
        return 'deliveries';
    }

    public function showUrl(): string
    {
        return route('delivery.deliveries.show', $this);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return HasMany<DeliveryLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(DeliveryLine::class)->orderBy('sort_order');
    }

    /** @return HasOne<PackingList, $this> */
    public function packingList(): HasOne
    {
        return $this->hasOne(PackingList::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'delivery_date', 'shipment_method', 'vehicle', 'tracking_number'])->logOnlyDirty()->useLogName('documents');
    }
}
