<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tax, bank charges, commission... withheld from what the customer paid.
 *
 * @property int $id
 * @property int $receipt_id
 * @property int $gl_account_id
 * @property string $amount
 * @property string|null $memo
 * @property-read GlAccount $account
 */
class ReceiptDeduction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }
}
