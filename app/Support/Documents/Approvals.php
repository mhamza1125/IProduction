<?php

namespace App\Support\Documents;

use App\Modules\Core\Services\Settings;
use App\Support\Modules\ModuleRegistry;

/**
 * Settings > Approvals: whether a document type must be approved before posting.
 */
class Approvals
{
    public function __construct(
        private readonly Settings $settings,
        private readonly ModuleRegistry $modules,
    ) {}

    public function required(string $documentType): bool
    {
        $saved = $this->settings->get("approval.{$documentType}");

        if ($saved !== null) {
            return (bool) $saved;
        }

        return $this->modules->documentType($documentType)->approvalByDefault ?? true;
    }

    public function set(string $documentType, bool $required): void
    {
        $this->settings->set("approval.{$documentType}", $required);
    }
}
