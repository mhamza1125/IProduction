<?php

namespace App\Modules\Production\Models;

use App\Modules\Core\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ptc_id
 * @property int $stage_id
 * @property int $sequence
 * @property bool $is_optional
 * @property string $work_type
 * @property string|null $wage_rate
 * @property string|null $contractor_rate
 * @property string|null $machine
 * @property string $issued_qty
 * @property string $received_qty
 * @property string $rejected_qty
 * @property bool $is_skipped
 * @property string|null $skip_reason
 * @property-read Stage $stage
 * @property-read Ptc $ptc
 */
class PtcStage extends Model
{
    protected $guarded = ['id'];

    /** @var list<string> */
    protected $with = ['stage'];

    protected $attributes = ['issued_qty' => '0', 'received_qty' => '0', 'rejected_qty' => '0', 'is_skipped' => false];

    protected function casts(): array
    {
        return ['is_optional' => 'boolean', 'is_skipped' => 'boolean', 'wage_rate' => 'decimal:4', 'contractor_rate' => 'decimal:4',
            'issued_qty' => 'decimal:4', 'received_qty' => 'decimal:4', 'rejected_qty' => 'decimal:4'];
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    /** @return BelongsTo<Ptc, $this> */
    public function ptc(): BelongsTo
    {
        return $this->belongsTo(Ptc::class);
    }
}
