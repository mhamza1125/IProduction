<?php

namespace App\Modules\Inventory\Models;

use App\Modules\People\Models\Contractor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $contractor_id
 * @property string|null $system_key
 * @property bool $is_active
 */
class Location extends Model
{
    use LogsActivity;

    public const STORE = 'store';

    public const PRODUCTION = 'production';

    public const CONTRACTOR = 'contractor';

    public const TYPES = [self::STORE => 'Store', self::PRODUCTION => 'Production floor', self::CONTRACTOR => 'Contractor'];

    protected $fillable = ['code', 'name', 'type', 'contractor_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function mainStore(): self
    {
        return static::query()->where('system_key', 'main_store')->firstOrFail();
    }

    public static function productionFloor(): self
    {
        return static::query()->where('system_key', 'production_floor')->firstOrFail();
    }

    public static function forContractor(Contractor $contractor): self
    {
        return static::query()->firstOrCreate(
            ['contractor_id' => $contractor->id],
            ['code' => 'C-'.$contractor->code, 'name' => $contractor->name, 'type' => self::CONTRACTOR],
        );
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class)->withTrashed();
    }

    /** @param Builder<Location> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
