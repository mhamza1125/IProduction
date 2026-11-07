<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Support\Decimal;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Rejected pieces, held in Rejected status until decisions are posted.
 *
 * @property int $id
 * @property string $number
 * @property Carbon $rejection_date
 * @property int|null $ptc_id
 * @property int|null $contractor_job_id
 * @property int $item_variant_id
 * @property int|null $stage_id
 * @property int $lot_id
 * @property int $location_id
 * @property string $quantity
 * @property string $decided_qty
 * @property string $reason
 * @property string|null $found_by
 * @property string|null $responsible_type
 * @property int|null $responsible_id
 * @property string|null $notes
 * @property string $status
 * @property-read ItemVariant $variant
 * @property-read Lot $lot
 * @property-read Stage|null $stage
 * @property-read Location $location
 * @property-read Model|null $responsible
 * @property-read Ptc|null $ptc
 */
class Rejection extends Model implements Attachable
{
    use HasAttachmentsAndNotes, LogsActivity;

    protected $guarded = ['id'];

    protected $attributes = ['decided_qty' => '0', 'status' => 'open'];

    protected function casts(): array
    {
        return ['rejection_date' => 'date', 'quantity' => 'decimal:4', 'decided_qty' => 'decimal:4'];
    }

    public function undecided(): string
    {
        return Decimal::sub($this->quantity, $this->decided_qty, 4);
    }

    public function permissionKey(): string
    {
        return 'rejections';
    }

    public function showUrl(): string
    {
        return route('production.rejections.show', $this);
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

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Ptc, $this> */
    public function ptc(): BelongsTo
    {
        return $this->belongsTo(Ptc::class);
    }

    /** @return BelongsTo<ContractorJob, $this> */
    public function job(): BelongsTo
    {
        return $this->belongsTo(ContractorJob::class, 'contractor_job_id');
    }

    /** @return MorphTo<Model, $this> */
    public function responsible(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<RejectionDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(RejectionDecision::class)->orderBy('id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'decided_qty', 'reason', 'notes'])->logOnlyDirty()->useLogName('documents');
    }
}
