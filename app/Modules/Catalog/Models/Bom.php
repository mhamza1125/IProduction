<?php

namespace App\Modules\Catalog\Models;

use App\Models\User;
use App\Support\Documents\DocumentException;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Bill of materials for one item. Versions: a draft is edited, approval
 * makes it the active version (the previous one becomes superseded).
 * Approved versions are never edited; changes go into a new version.
 *
 * @property int $id
 * @property int $item_id
 * @property int $version
 * @property Carbon $effective_date
 * @property string $output_quantity
 * @property string $status
 * @property bool $is_active
 * @property string|null $notes
 * @property Carbon|null $approved_at
 */
class Bom extends Model implements Attachable
{
    use HasAttachmentsAndNotes, LogsActivity;

    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const SUPERSEDED = 'superseded';

    protected $fillable = ['item_id', 'version', 'effective_date', 'output_quantity', 'notes', 'created_by'];

    protected $attributes = ['status' => self::DRAFT, 'is_active' => false];

    protected function casts(): array
    {
        return ['effective_date' => 'date', 'output_quantity' => 'decimal:4', 'is_active' => 'boolean', 'approved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(function (Bom $bom) {
            if (($bom->getOriginal('status') ?? self::DRAFT) !== self::DRAFT && array_diff(array_keys($bom->getDirty()), ['status', 'is_active', 'updated_at']) !== []) {
                throw new DocumentException("Version {$bom->version} is approved and cannot be edited. Create a new version.");
            }
        });

        static::deleting(function (Bom $bom) {
            if ($bom->status !== self::DRAFT) {
                throw new DocumentException('Only draft BOM versions can be deleted.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === self::DRAFT;
    }

    public function permissionKey(): string
    {
        return 'boms';
    }

    /** @return BelongsTo<Item, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** @return HasMany<BomLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(BomLine::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return HasMany<BomOverride, $this> */
    public function overrides(): HasMany
    {
        return $this->hasMany(BomOverride::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['version', 'effective_date', 'output_quantity', 'status', 'is_active', 'notes'])->logOnlyDirty();
    }
}
