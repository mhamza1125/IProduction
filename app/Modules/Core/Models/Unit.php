<?php

namespace App\Modules\Core\Models;

use App\Support\Models\GuardsUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $name
 * @property string $symbol
 * @property bool $allows_decimal
 * @property bool $is_active
 */
class Unit extends Model
{
    use GuardsUsage, LogsActivity, SoftDeletes;

    /** Default units from the specification: name => [symbol, decimals allowed]. */
    public const DEFAULTS = [
        'Piece' => ['pcs', false], 'Pair' => ['pair', false], 'Dozen' => ['dozen', true],
        'Kilogram' => ['kg', true], 'Gram' => ['g', true], 'Sheet' => ['sheet', true],
        'Roll' => ['roll', true], 'Metre' => ['m', true], 'Foot' => ['ft', true],
        'Litre' => ['L', true], 'Millilitre' => ['mL', true], 'Box' => ['box', false],
    ];

    protected $fillable = ['name', 'symbol', 'allows_decimal', 'is_active'];

    protected function casts(): array
    {
        return ['allows_decimal' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @param Builder<Unit> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
