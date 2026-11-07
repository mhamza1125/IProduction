<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\ItemVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BOM copy: material per output unit (wastage included, stock unit).
 *
 * @property int $id
 * @property int $ptc_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string $per_unit
 * @property string $wastage_percent
 * @property bool $is_optional
 * @property-read ItemVariant $variant
 */
class PtcMaterial extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['per_unit' => 'decimal:6', 'wastage_percent' => 'decimal:2', 'is_optional' => 'boolean'];
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }
}
