<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * 1 from-unit = factor to-units. Item-specific factors override general ones.
 *
 * @property int $id
 * @property int $from_unit_id
 * @property int $to_unit_id
 * @property string $factor
 * @property int|null $item_id
 */
class UnitConversion extends Model
{
    use LogsActivity;

    protected $fillable = ['from_unit_id', 'to_unit_id', 'factor', 'item_id'];

    protected function casts(): array
    {
        return ['factor' => 'decimal:6'];
    }

    /** @return BelongsTo<Unit, $this> */
    public function fromUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'from_unit_id')->withTrashed();
    }

    /** @return BelongsTo<Unit, $this> */
    public function toUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'to_unit_id')->withTrashed();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
