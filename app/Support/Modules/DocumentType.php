<?php

namespace App\Support\Modules;

/**
 * A kind of document (Sales Order, Goods Receipt...). Modules declare them so
 * that numbering and approval settings can list every type in one place.
 */
final readonly class DocumentType
{
    public function __construct(
        /** Stable key, e.g. "sales_order". Used for numbering and approval settings. */
        public string $key,
        public string $label,
        /** Default number prefix, e.g. "SO" gives SO-26-0001. */
        public string $prefix,
        /** Whether approval is required before posting, until changed in Settings. */
        public bool $approvalByDefault = true,
        /** False for master-data codes (customer codes...), which have numbering but no approval. */
        public bool $approvable = true,
        /** Default year part: YY (SO-26-0001), YYYY, or none (CUS-0001). */
        public string $yearFormat = 'YY',
    ) {}
}
