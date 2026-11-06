<?php

namespace App\Modules\Finance\Models;

use App\Modules\Core\Models\Currency;
use App\Support\Models\Attachable;
use App\Support\Models\GuardsUsage;
use App\Support\Models\HasAttachmentsAndNotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A cash or bank account. The Cash Book is permanent. Payments, receipts,
 * transfers and conversions move money between these.
 *
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string|null $bank_name
 * @property string|null $branch
 * @property string|null $account_title
 * @property string|null $account_number
 * @property string|null $iban
 * @property string $currency_code
 * @property int $gl_account_id
 * @property bool $allow_overdraft
 * @property string|null $system_key
 * @property bool $is_active
 * @property string|null $notes
 */
class MoneyAccount extends Model implements Attachable
{
    use GuardsUsage, HasAttachmentsAndNotes, LogsActivity;

    public const CASH = 'cash';

    public const BANK = 'bank';

    public const TYPES = [self::CASH => 'Cash', self::BANK => 'Bank'];

    protected $fillable = ['name', 'type', 'bank_name', 'branch', 'account_title', 'account_number', 'iban', 'currency_code', 'gl_account_id', 'allow_overdraft', 'is_active', 'notes'];

    protected function casts(): array
    {
        return ['allow_overdraft' => 'boolean', 'is_active' => 'boolean'];
    }

    public static function cashBook(): self
    {
        return static::query()->where('system_key', 'cash_book')->firstOrFail();
    }

    public function permissionKey(): string
    {
        return 'accounts';
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class);
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }

    /** @param Builder<MoneyAccount> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
