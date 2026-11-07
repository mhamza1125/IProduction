<?php

namespace App\Modules\Production\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * Signed PKR cost entry on a PTC. Positive: cost added (material, wage,
 * contractor, rework, moved in). Negative: cost moved out (released to
 * stock, split or merged to another PTC).
 *
 * @property int $id
 * @property int $ptc_id
 * @property string $category
 * @property string $amount
 * @property string|null $memo
 */
class PtcCost extends Model
{
    public const CATEGORIES = [
        'material' => 'Material',
        'extra_material' => 'Extra material',
        'stock_in' => 'Stock entered',
        'wage' => 'Wage work',
        'contractor' => 'Contractor',
        'rework' => 'Rework',
        'transfer_in' => 'Moved in',
        'transfer_out' => 'Moved out',
        'released' => 'Released to stock',
    ];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cost entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Cost entries are immutable.'));
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
