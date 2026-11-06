<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $payment_id
 * @property string $allocatable_type
 * @property int $allocatable_id
 * @property string $amount
 * @property int|null $journal_id
 */
class PaymentAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /** @return MorphTo<Model, $this> */
    public function allocatable(): MorphTo
    {
        return $this->morphTo();
    }
}
