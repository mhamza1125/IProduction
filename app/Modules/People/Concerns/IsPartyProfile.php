<?php

namespace App\Modules\People\Concerns;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\People\Models\Party;
use App\Support\Models\GuardsUsage;
use App\Support\Models\HasAttachmentsAndNotes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Shared behaviour of customers, vendors, employees and contractors:
 * a party link, an automatic code, active scope, soft deletes that are
 * blocked once the profile is used, attachments, notes and an audit trail.
 *
 * @property int $party_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 */
trait IsPartyProfile
{
    use GuardsUsage, HasAttachmentsAndNotes, LogsActivity, SoftDeletes;

    /** Numbering key for the code, e.g. "customer" gives CUS-0001. */
    abstract public static function codeType(): string;

    public static function bootIsPartyProfile(): void
    {
        static::creating(function (self $profile) {
            if (blank($profile->code)) {
                $profile->code = DB::transaction(fn () => app(DocumentNumber::class)->next(static::codeType()));
            }

            if (! $profile->getAttribute('party_id')) {
                $profile->party_id = Party::query()->create(['name' => $profile->name])->id;
            }
        });
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @param Builder<static> $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->code.' · '.$this->name;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
