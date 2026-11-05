<?php

namespace App\Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $lot_id
 * @property int $source_lot_id
 * @property string|null $quantity
 */
class LotLink extends Model
{
    protected $fillable = ['lot_id', 'source_lot_id', 'quantity', 'document_type', 'document_id'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }

    /** @return BelongsTo<Lot, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class);
    }

    /** @return BelongsTo<Lot, $this> */
    public function sourceLot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'source_lot_id');
    }

    /** @return MorphTo<Model, $this> */
    public function document(): MorphTo
    {
        return $this->morphTo();
    }
}
