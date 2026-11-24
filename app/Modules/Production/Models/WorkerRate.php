<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Stage;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An individual piece rate for one worker at one stage (optionally one item),
 * from a date onwards. A revision is a new row with a later effective date, so
 * the rate in force on any past day can always be found again.
 *
 * @property int $id
 * @property string $worker_type employee | contractor
 * @property int|null $employee_id
 * @property int|null $contractor_id
 * @property int $stage_id
 * @property int|null $item_id
 * @property string $rate
 * @property Carbon $effective_from
 * @property string|null $notes
 * @property bool $is_active
 * @property-read Employee|null $employee
 * @property-read Contractor|null $contractor
 * @property-read Stage $stage
 * @property-read Item|null $item
 */
class WorkerRate extends Model
{
    use LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'is_active' => 'boolean', 'rate' => 'decimal:4'];
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<Contractor, $this> */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function workerName(): string
    {
        return (string) ($this->worker_type === 'employee' ? $this->employee?->name : $this->contractor?->name);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['rate', 'effective_from', 'item_id', 'is_active', 'notes'])->logOnlyDirty();
    }
}
