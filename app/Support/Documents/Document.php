<?php

namespace App\Support\Documents;

use App\Support\Models\PermissionScoped;
use Carbon\CarbonInterface;

/**
 * Implemented by every document model that uses the posting framework.
 * Use the HasDocumentWorkflow trait for the implementation.
 */
interface Document extends PermissionScoped
{
    /** Key of the DocumentType, e.g. "sales_order". */
    public function documentType(): string;

    /** The date that decides the number's year and the period lock. */
    public function documentDate(): CarbonInterface;
}
