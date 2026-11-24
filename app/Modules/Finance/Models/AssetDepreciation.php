<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One month's depreciation of one asset, and the journal that posted it.
 *
 * @property int $id
 * @property int $asset_id
 * @property Carbon $period first day of the month
 * @property string $amount
 * @property int $journal_id
 * @property-read Asset $asset
 * @property-read Journal $journal
 */
class AssetDepreciation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['period' => 'date', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Asset, $this> */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }
}
