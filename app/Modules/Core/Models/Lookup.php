<?php

namespace App\Modules\Core\Models;

use App\Support\Models\GuardsUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Short pick-lists managed in Settings > Lookups.
 *
 * @property int $id
 * @property string $type
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
class Lookup extends Model
{
    use GuardsUsage, LogsActivity, SoftDeletes;

    /** Lookup types from the specification. */
    public const TYPES = [
        'item_category' => 'Item categories',
        'department' => 'Departments',
        'designation' => 'Designations',
        'rejection_reason' => 'Rejection reasons',
        'wastage_reason' => 'Wastage reasons',
        'adjustment_reason' => 'Stock adjustment reasons',
        'payment_method' => 'Payment methods',
        'deduction' => 'Deductions',
        'allowance' => 'Allowances',
    ];

    protected $fillable = ['type', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @param Builder<Lookup> $query */
    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('type', $type)->orderBy('sort_order')->orderBy('name');
    }

    /** @param Builder<Lookup> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Active options of one type for a select: id => name.
     *
     * @return array<int, string>
     */
    public static function options(string $type, ?int $include = null): array
    {
        return static::query()->ofType($type)
            ->where(fn ($q) => $q->where('is_active', true)->when($include, fn ($q) => $q->orWhere('id', $include)))
            ->pluck('name', 'id')
            ->all();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
