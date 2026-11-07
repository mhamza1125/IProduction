<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $delivery_id
 * @property string|null $notes
 * @property Carbon|null $completed_at
 * @property-read Delivery $delivery
 */
class PackingList extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    /** @return BelongsTo<Delivery, $this> */
    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    /** @return HasMany<Carton, $this> */
    public function cartons(): HasMany
    {
        return $this->hasMany(Carton::class)->orderBy('carton_number');
    }
}
