<?php

namespace App\Support\Documents;

/**
 * The lifecycle shared by operational and financial documents:
 * Draft → Approved → Posted, or Cancelled before anything is posted.
 * Documents may add their own progress statuses (e.g. "partly_delivered");
 * every status other than Draft is locked against editing.
 */
final class DocumentStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const POSTED = 'posted';

    public const CANCELLED = 'cancelled';

    /** @var array<string, string> label per core status */
    public const LABELS = [
        self::DRAFT => 'Draft',
        self::APPROVED => 'Approved',
        self::POSTED => 'Posted',
        self::CANCELLED => 'Cancelled',
    ];
}
