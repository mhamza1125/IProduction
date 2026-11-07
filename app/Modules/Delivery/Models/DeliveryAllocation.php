<?php

namespace App\Modules\Delivery\Models;

use App\Modules\Inventory\Models\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $delivery_line_id
 * @property int $lot_id
 * @property int $location_id
 * @property int|null $stage_id
 * @property string $status
 * @property string $quantity
 * @property string|null $unit_cost
 * @property-read Lot $lot
 */
class DeliveryAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_cost' => 'decimal:4'];
    }

    /** @return BelongsTo<DeliveryLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(DeliveryLine::class, 'delivery_line_id');
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
