<?php

namespace App\Modules\Sales\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Core\Models\Unit;
use App\Modules\Inventory\Models\Reservation;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Quantities are in the item's stock unit; prices in the order currency.
 *
 * @property int $id
 * @property int $sales_order_id
 * @property int $item_variant_id
 * @property int $sort_order
 * @property string|null $description
 * @property string $quantity
 * @property int $unit_id
 * @property string $unit_price
 * @property string|null $tax_rate
 * @property string $line_total
 * @property Carbon|null $due_date
 * @property string $plan_buy
 * @property string $plan_make
 * @property string $plan_contractor
 * @property int|null $route_id
 * @property string $delivered_qty
 * @property string $invoiced_qty
 * @property string $returned_qty
 * @property string $closed_qty
 */
class SalesOrderLine extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        $q = 'decimal:4';

        return ['quantity' => $q, 'unit_price' => $q, 'tax_rate' => 'decimal:2', 'line_total' => 'decimal:2', 'due_date' => 'date',
            'plan_buy' => $q, 'plan_make' => $q, 'plan_contractor' => $q, 'delivered_qty' => $q, 'invoiced_qty' => $q, 'returned_qty' => $q, 'closed_qty' => $q];
    }

    /** Still to deliver: ordered − delivered − closed (returns with replacement reopen it later). */
    public function remaining(): string
    {
        $left = Decimal::sub(Decimal::sub($this->quantity, $this->delivered_qty, 4), $this->closed_qty, 4);

        return Decimal::isNegative($left) ? '0.0000' : $left;
    }

    public function reservationLabel(): string
    {
        return $this->order->number.' line '.($this->sort_order + 1);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /** @return BelongsTo<ProductionRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(ProductionRoute::class, 'route_id')->withTrashed();
    }

    /** @return MorphMany<Reservation, $this> */
    public function reservations(): MorphMany
    {
        return $this->morphMany(Reservation::class, 'demand');
    }

    /** @return HasMany<SupplyAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SupplyAllocation::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['quantity', 'unit_price', 'plan_buy', 'plan_make', 'plan_contractor', 'closed_qty'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
