<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Credit note (or later a receipt) set against an invoice.
 *
 * @property int $id
 * @property int $invoice_id
 * @property string $source_type
 * @property int $source_id
 * @property Carbon $allocation_date
 * @property string $amount
 */
class InvoiceAllocation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allocation_date' => 'date', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<Invoice, $this> */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }
}
