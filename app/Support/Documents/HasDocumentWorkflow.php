<?php

namespace App\Support\Documents;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Lifecycle columns and locking for document models.
 *
 * Expected columns: number (unique), status, approved_by, approved_at,
 * posted_by, posted_at, cancelled_by, cancelled_at, cancel_reason.
 * Add them with the Blueprint macro: $table->documentWorkflow();
 *
 * Once a document leaves Draft it is locked: only the workflow columns and
 * the ones listed in $mutableWhenLocked may change, and it cannot be deleted.
 *
 * @property string $number
 * @property string $status
 * @property Carbon|null $approved_at
 * @property Carbon|null $posted_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
trait HasDocumentWorkflow
{
    /** Columns the workflow itself writes after a document is locked. */
    private static array $workflowColumns = [
        'status', 'approved_by', 'approved_at', 'posted_by', 'posted_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason', 'updated_at',
    ];

    public static function bootHasDocumentWorkflow(): void
    {
        static::updating(function (self $document) {
            if ($document->getOriginal('status') === DocumentStatus::DRAFT || $document->getOriginal('status') === null) {
                return;
            }

            $allowed = array_merge(self::$workflowColumns, $document->mutableWhenLocked());
            $changed = array_diff(array_keys($document->getDirty()), $allowed);

            if ($changed !== []) {
                throw new DocumentException(
                    "{$document->number} is ".str_replace('_', ' ', (string) $document->getOriginal('status')).' and cannot be edited.'
                );
            }
        });

        static::deleting(function (self $document) {
            if ($document->status !== DocumentStatus::DRAFT) {
                throw new DocumentException("{$document->number} is not a draft and cannot be deleted. Cancel or reverse it instead.");
            }
        });
    }

    /**
     * Extra columns that may change after the document is locked, such as
     * progress quantities maintained by later documents.
     *
     * @return list<string>
     */
    public function mutableWhenLocked(): array
    {
        return [];
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::DRAFT;
    }

    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::APPROVED;
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->status === DocumentStatus::CANCELLED;
    }

    public function isEditable(): bool
    {
        return $this->isDraft();
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
