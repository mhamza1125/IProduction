<?php

namespace App\Modules\Production\Models;

use App\Modules\Core\Models\Stage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Child / split / merge link: quantity moved from source_ptc to ptc.
 *
 * @property int $id
 * @property int $ptc_id
 * @property int $source_ptc_id
 * @property string $type
 * @property string $quantity
 * @property int|null $stage_id
 * @property-read Ptc $ptc
 * @property-read Ptc $source
 */
class PtcLink extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<Ptc, $this> */
    public function ptc(): BelongsTo
    {
        return $this->belongsTo(Ptc::class);
    }

    /** @return BelongsTo<Ptc, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Ptc::class, 'source_ptc_id');
    }

    /** @return BelongsTo<Stage, $this> */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }
}
