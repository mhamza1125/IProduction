<?php

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $journal_voucher_id
 * @property int|null $gl_account_id
 * @property int|null $money_account_id
 * @property string|null $party_type
 * @property int|null $party_id
 * @property string $debit
 * @property string $credit
 * @property string|null $memo
 * @property bool $deduct_from_payroll
 * @property int|null $payroll_line_id
 * @property-read GlAccount|null $account
 * @property-read MoneyAccount|null $moneyAccount
 * @property-read Model|null $party
 */
class JournalVoucherLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'deduct_from_payroll' => 'boolean'];
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id');
    }

    /** @return BelongsTo<JournalVoucher, $this> */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(JournalVoucher::class, 'journal_voucher_id');
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }

    /** @return MorphTo<Model, $this> */
    public function party(): MorphTo
    {
        return $this->morphTo();
    }
}
