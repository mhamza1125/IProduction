<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $receipt_id
 * @property string $receivable_type
 * @property int $receivable_id
 * @property Carbon $allocation_date
 * @property string $amount
 * @property bool $from_advance
 * @property-read Model|null $receivable
 */
class ReceiptAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allocation_date' => 'date', 'amount' => 'decimal:2', 'from_advance' => 'boolean'];
    }

    /** @return BelongsTo<Receipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(Receipt::class);
    }

    /** @return MorphTo<Model, $this> */
    public function receivable(): MorphTo
    {
        return $this->morphTo();
    }
}
