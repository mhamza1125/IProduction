<?php

namespace App\Modules\Production\Models;

use App\Models\User;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Inventory\Models\Lot;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One stage transaction (specification, Module 7). Immutable.
 *
 * @property int $id
 * @property int $ptc_id
 * @property int|null $ptc_stage_id
 * @property string $type
 * @property Carbon $transaction_date
 * @property string|null $worker_type
 * @property int|null $employee_id
 * @property int|null $contractor_id
 * @property int $item_variant_id
 * @property int|null $lot_id
 * @property int|null $location_id
 * @property string $quantity
 * @property string|null $unit_cost
 * @property string|null $amount
 * @property int|null $related_ptc_id
 * @property string|null $reason
 * @property string|null $notes
 * @property-read PtcStage|null $ptcStage
 * @property-read ItemVariant $variant
 * @property-read Employee|null $employee
 * @property-read Contractor|null $contractor
 */
class PtcTransaction extends Model
{
    public const TYPES = [
        'material_issue' => 'Material Issue',
        'extra_issue' => 'Extra Issue',
        'stage_issue' => 'Stage Issue',
        'stage_receive' => 'Stage Receive',
        'reject' => 'Reject',
        'return_to_store' => 'Return to Store',
        'wastage' => 'Wastage',
        'consume' => 'Material Consumed',
        'release' => 'Release',
        'skip' => 'Stage Skipped',
        'merge_in' => 'Merged In',
        'merge_out' => 'Merged Out',
        'split_in' => 'Split In',
        'split_out' => 'Split Out',
        'rework_in' => 'Rework In',
    ];

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'quantity' => 'decimal:4', 'unit_cost' => 'decimal:4', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Production transactions are immutable.'));
        static::deleting(fn () => throw new LogicException('Production transactions are immutable.'));
    }

    public function label(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function workerName(): ?string
    {
        return $this->employee->name ?? $this->contractor?->name;
    }

    /** @return BelongsTo<PtcStage, $this> */
    public function ptcStage(): BelongsTo
    {
        return $this->belongsTo(PtcStage::class);
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

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
