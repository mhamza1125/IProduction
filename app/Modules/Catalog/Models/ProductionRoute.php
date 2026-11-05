<?php

namespace App\Modules\Catalog\Models;

use App\Support\Models\GuardsUsage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An ordered list of stages for an item that is made. A PTC copies the route
 * when it is created, so later changes only affect new PTCs.
 *
 * @property int $id
 * @property int $item_id
 * @property string $name
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $notes
 */
class ProductionRoute extends Model
{
    use GuardsUsage, LogsActivity, SoftDeletes;

    protected $table = 'production_routes';

    protected $fillable = ['item_id', 'name', 'is_default', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return HasMany<RouteStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(RouteStage::class, 'route_id')->orderBy('sequence');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
