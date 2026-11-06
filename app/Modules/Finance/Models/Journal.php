<?php

namespace App\Modules\Finance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property string $number
 * @property Carbon $journal_date
 * @property string $currency_code
 * @property string $description
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int|null $reverses_journal_id
 */
class Journal extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['journal_date' => 'date'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Journals are immutable. Post a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Journals are immutable. Post a reversal instead.'));
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Journal, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_journal_id');
    }

    /** @return HasOne<Journal, $this> */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_journal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
