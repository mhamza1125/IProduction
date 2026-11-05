<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Core\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $route_id
 * @property int $stage_id
 * @property int $sequence
 * @property bool $is_optional
 * @property string $default_work_type
 * @property string|null $wage_rate
 * @property string|null $contractor_rate
 * @property string|null $machine
 * @property string|null $instructions
 */
class RouteStage extends Model
{
    public const WORK_TYPES = ['employee' => 'Employee', 'contractor' => 'Contractor'];

    protected $fillable = [
        'route_id', 'stage_id', 'sequence', 'is_optional', 'default_work_type', 'wage_rate', 'contractor_rate', 'machine', 'instructions',
    ];

    protected function casts(): array
    {
        return ['is_optional' => 'boolean', 'wage_rate' => 'decimal:4', 'contractor_rate' => 'decimal:4'];
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class)->withTrashed();
    }

    /** @return BelongsTo<ProductionRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(ProductionRoute::class, 'route_id');
    }
}
