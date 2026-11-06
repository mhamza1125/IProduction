<?php

namespace App\Modules\Finance\Models;

use App\Modules\People\Models\Party;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Immutable. Ledgers are built from these lines; nobody edits a balance.
 *
 * @property int $id
 * @property int $journal_id
 * @property Carbon $line_date
 * @property int $gl_account_id
 * @property string $currency_code
 * @property string $debit
 * @property string $credit
 * @property string|null $ledgerable_type
 * @property int|null $ledgerable_id
 * @property int|null $party_id
 * @property int|null $money_account_id
 * @property string|null $memo
 */
class JournalLine extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['line_date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Journal lines are immutable.'));
        static::deleting(fn () => throw new LogicException('Journal lines are immutable.'));
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'gl_account_id')->withTrashed();
    }

    /** @return MorphTo<Model, $this> */
    public function ledgerable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function moneyAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class);
    }
}
