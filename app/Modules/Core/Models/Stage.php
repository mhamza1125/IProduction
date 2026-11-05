<?php

namespace App\Modules\Core\Models;

use App\Support\Models\GuardsUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A production step such as Stitching or Embossing.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property int $sort_order
 * @property bool $is_active
 */
class Stage extends Model
{
    use GuardsUsage, LogsActivity, SoftDeletes;

    protected $fillable = ['name', 'description', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @param Builder<Stage> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Stage> $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('name');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
