<?php

namespace App\Modules\Finance\Models;

use App\Modules\People\Models\Employee;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Asset register entry (record only: no accounts, no stock).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $category
 * @property Carbon|null $purchase_date
 * @property string|null $value
 * @property string|null $location
 * @property int|null $assigned_employee_id
 * @property string|null $serial_number
 * @property string $status
 * @property string|null $notes
 * @property-read Employee|null $assignedTo
 */
class Asset extends Model implements Attachable
{
    use HasAttachmentsAndNotes, LogsActivity, SoftDeletes;

    public const STATUSES = ['in_use' => 'In use', 'in_store' => 'In store', 'in_repair' => 'In repair', 'disposed' => 'Disposed', 'lost' => 'Lost'];

    protected $fillable = ['code', 'name', 'category', 'purchase_date', 'value', 'location', 'assigned_employee_id', 'serial_number', 'status', 'notes'];

    protected function casts(): array
    {
        return ['purchase_date' => 'date', 'value' => 'decimal:2'];
    }

    public function permissionKey(): string
    {
        return 'assets';
    }

    /** @return BelongsTo<Employee, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['code', 'name', 'category', 'value', 'location', 'assigned_employee_id', 'status'])->logOnlyDirty()->useLogName('masters');
    }
}
