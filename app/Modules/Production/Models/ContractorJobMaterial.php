<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\ItemVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contractor_job_id
 * @property int $item_variant_id
 * @property string $expected_qty
 * @property-read ItemVariant $variant
 */
class ContractorJobMaterial extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_qty' => 'decimal:4'];
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }
}
