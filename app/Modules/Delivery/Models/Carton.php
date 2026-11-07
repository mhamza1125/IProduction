<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $packing_list_id
 * @property int $carton_number
 * @property string|null $carton_type
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property string|null $gross_weight
 * @property string|null $net_weight
 * @property string|null $marks
 * @property string|null $notes
 */
class Carton extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['length' => 'decimal:2', 'width' => 'decimal:2', 'height' => 'decimal:2', 'gross_weight' => 'decimal:3', 'net_weight' => 'decimal:3'];
    }

    /** @return BelongsTo<PackingList, $this> */
    public function packingList(): BelongsTo
    {
        return $this->belongsTo(PackingList::class);
    }

    /** @return HasMany<CartonLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(CartonLine::class);
    }
}
