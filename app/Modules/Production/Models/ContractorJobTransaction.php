<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Inventory\Models\Lot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Material issued, returned, wasted or consumed at a contractor, or output received. Immutable.
 *
 * @property int $id
 * @property int $contractor_job_id
 * @property int|null $contractor_job_line_id
 * @property string $type
 * @property Carbon $transaction_date
 * @property int $item_variant_id
 * @property int|null $lot_id
 * @property string $quantity
 * @property string|null $accepted_qty
 * @property string|null $rejected_qty
 * @property string|null $paid_qty
 * @property string|null $over_receipt_action
 * @property string|null $unit_cost
 * @property string|null $amount
 * @property int|null $journal_id
 * @property string|null $reason
 * @property-read ItemVariant $variant
 * @property-read Lot|null $lot
 */
class ContractorJobTransaction extends Model
{
    public const TYPES = ['issue' => 'Material issued', 'return' => 'Returned to store', 'wastage' => 'Wastage', 'consume' => 'Consumed', 'receipt' => 'Output received'];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'quantity' => 'decimal:4', 'accepted_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4',
            'paid_qty' => 'decimal:4', 'unit_cost' => 'decimal:4', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Contractor job transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Contractor job transactions are immutable.'));
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }
}
