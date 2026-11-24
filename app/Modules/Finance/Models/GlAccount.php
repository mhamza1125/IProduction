<?php

namespace App\Modules\Finance\Models;

use App\Support\Documents\DocumentException;
use App\Support\Models\GuardsUsage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * An account in the chart of accounts.
 *
 * Posting code finds accounts by system_key, never by code or name:
 *   GlAccount::system('accounts_receivable')
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $type
 * @property int|null $parent_id
 * @property string|null $system_key
 * @property string|null $description
 * @property bool $is_active
 * @property string|null $term current | non_current (assets and liabilities)
 */
class GlAccount extends Model
{
    use GuardsUsage, LogsActivity, SoftDeletes;

    public const TYPES = [
        'asset' => 'Assets',
        'liability' => 'Liabilities',
        'equity' => 'Equity',
        'income' => 'Income',
        'cost' => 'Cost',
        'expense' => 'Expenses',
        'clearing' => 'Clearing',
    ];

    /** Debit-normal types: balance = debits − credits. Others are credit-normal. */
    public const DEBIT_NORMAL = ['asset', 'cost', 'expense', 'clearing'];

    protected $fillable = ['code', 'name', 'type', 'term', 'parent_id', 'description', 'is_active'];

    /** Balance sheet classification of assets and liabilities. */
    public const TERMS = ['current' => 'Current', 'non_current' => 'Non-current'];

    public function hasTerm(): bool
    {
        return in_array($this->type, ['asset', 'liability', 'clearing'], true);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Assets and liabilities are current unless classified otherwise; other types have no term.
        static::saving(function (GlAccount $account) {
            if (! $account->hasTerm()) {
                $account->term = null;
            } elseif ($account->term === null) {
                $account->term = 'current';
            }
        });

        static::updating(function (GlAccount $account) {
            if ($account->system_key !== null && $account->isDirty(['type', 'system_key'])) {
                throw new DocumentException('System accounts cannot change type.');
            }

            if ($account->system_key !== null && $account->isDirty('is_active') && ! $account->is_active) {
                throw new DocumentException('System accounts cannot be deactivated.');
            }
        });

        static::deleting(function (GlAccount $account) {
            if ($account->system_key !== null) {
                throw new DocumentException('System accounts cannot be deleted.');
            }
        });
    }

    public static function system(string $key): self
    {
        return static::query()->where('system_key', $key)->firstOrFail();
    }

    public function isDebitNormal(): bool
    {
        return in_array($this->type, self::DEBIT_NORMAL, true);
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @param Builder<GlAccount> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
