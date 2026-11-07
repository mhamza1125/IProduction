<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $contractor_job_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property string $quantity
 * @property string $rate
 * @property string $received_qty
 * @property string $accepted_qty
 * @property string $rejected_qty
 * @property string $paid_qty
 * @property int $sort_order
 * @property-read ItemVariant $variant
 * @property-read Stage|null $stage
 * @property-read ContractorJob $job
 */
class ContractorJobLine extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['received_qty' => '0', 'accepted_qty' => '0', 'rejected_qty' => '0', 'paid_qty' => '0'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'rate' => 'decimal:4', 'received_qty' => 'decimal:4', 'accepted_qty' => 'decimal:4',
            'rejected_qty' => 'decimal:4', 'paid_qty' => 'decimal:4'];
    }

    /** Agreed quantity not yet returned (never negative). */
    public function short(): string
    {
        $left = Decimal::sub($this->quantity, $this->received_qty, 4);

        return Decimal::isNegative($left) ? '0.0000' : $left;
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<ContractorJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ContractorJob::class, 'contractor_job_id');
    }
}
