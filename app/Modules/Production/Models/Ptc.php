<?php

namespace App\Modules\Production\Models;

use App\Modules\Catalog\Models\Bom;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Models\Lot;
use App\Modules\People\Models\Employee;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Support\Decimal;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Product Travel Card: one production job for one variant, followed stage by stage.
 *
 * @property int $id
 * @property int|null $sales_order_line_id
 * @property int $item_variant_id
 * @property string $planned_qty
 * @property int $route_id
 * @property int|null $bom_id
 * @property int|null $start_stage_id
 * @property int|null $end_stage_id
 * @property int|null $parent_id
 * @property string|null $parent_qty
 * @property bool $required_by_parent
 * @property int|null $responsible_employee_id
 * @property Carbon $start_date
 * @property Carbon|null $target_date
 * @property string|null $notes
 * @property int|null $output_lot_id
 * @property string $released_qty
 * @property string $merged_out_qty
 * @property string|null $close_reason
 * @property Carbon|null $completed_at
 * @property-read ItemVariant $variant
 * @property-read Lot|null $outputLot
 * @property-read SalesOrderLine|null $orderLine
 * @property-read ProductionRoute $route
 * @property-read Ptc|null $parent
 */
class Ptc extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    public const STATUSES = ['draft', 'approved', 'in_progress', 'on_hold', 'completed', 'closed_early', 'cancelled'];

    /** Statuses in which stage transactions are allowed. */
    public const ACTIVE = ['approved', 'in_progress'];

    public const FINISHED = ['completed', 'closed_early', 'cancelled'];

    protected $fillable = [
        'number', 'sales_order_line_id', 'item_variant_id', 'planned_qty', 'route_id', 'bom_id', 'start_stage_id', 'end_stage_id',
        'parent_id', 'parent_qty', 'required_by_parent', 'responsible_employee_id', 'start_date', 'target_date', 'notes', 'created_by',
    ];

    protected $attributes = ['released_qty' => '0', 'merged_out_qty' => '0', 'required_by_parent' => true];

    protected function casts(): array
    {
        return [
            'planned_qty' => 'decimal:4', 'parent_qty' => 'decimal:4', 'released_qty' => 'decimal:4', 'merged_out_qty' => 'decimal:4',
            'required_by_parent' => 'boolean', 'start_date' => 'date', 'target_date' => 'date', 'completed_at' => 'datetime',
            'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['output_lot_id', 'released_qty', 'merged_out_qty', 'close_reason', 'completed_at', 'responsible_employee_id', 'target_date', 'notes'];
    }

    public function documentType(): string
    {
        return 'ptc';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->start_date;
    }

    public function permissionKey(): string
    {
        return 'ptcs';
    }

    public function showUrl(): string
    {
        return route('production.ptcs.show', $this);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE, true);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, self::FINISHED, true);
    }

    public function plannedOpen(): string
    {
        return Decimal::sub($this->planned_qty, $this->merged_out_qty, 4);
    }

    /** @return BelongsTo<ItemVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ItemVariant::class, 'item_variant_id')->withTrashed();
    }

    /** @return BelongsTo<SalesOrderLine, $this> */
    public function orderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    /** @return BelongsTo<ProductionRoute, $this> */
    public function route(): BelongsTo
    {
        return $this->belongsTo(ProductionRoute::class, 'route_id')->withTrashed();
    }

    /** @return BelongsTo<Bom, $this> */
    public function bom(): BelongsTo
    {
        return $this->belongsTo(Bom::class);
    }

    /** @return BelongsTo<Stage, $this> */
    public function startStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'start_stage_id');
    }

    /** @return BelongsTo<Stage, $this> */
    public function endStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'end_stage_id');
    }

    /** @return BelongsTo<Ptc, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Ptc::class, 'parent_id');
    }

    /** @return HasMany<Ptc, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Ptc::class, 'parent_id');
    }

    /** @return BelongsTo<Employee, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'responsible_employee_id');
    }

    /** @return BelongsTo<Lot, $this> */
    public function outputLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'output_lot_id');
    }

    /** @return HasMany<PtcStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(PtcStage::class)->orderBy('sequence');
    }

    /** @return HasMany<PtcMaterial, $this> */
    public function materials(): HasMany
    {
        return $this->hasMany(PtcMaterial::class);
    }

    /** @return HasMany<PtcTransaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(PtcTransaction::class)->orderBy('id');
    }

    /** @return HasMany<PtcCost, $this> */
    public function costs(): HasMany
    {
        return $this->hasMany(PtcCost::class);
    }

    /** @return HasMany<PtcLink, $this> */
    public function links(): HasMany
    {
        return $this->hasMany(PtcLink::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'planned_qty', 'target_date', 'responsible_employee_id', 'close_reason'])->logOnlyDirty()->useLogName('documents');
    }
}
