<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A way items vary, e.g. Color or Size.
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 */
class Attribute extends Model
{
    use LogsActivity;

    protected $fillable = ['name', 'sort_order'];

    /** @return HasMany<AttributeValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('sort_order')->orderBy('value');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
