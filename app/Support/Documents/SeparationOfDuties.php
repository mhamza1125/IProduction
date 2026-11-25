<?php

namespace App\Support\Documents;

use Illuminate\Database\Eloquent\Model;

/**
 * Whoever created a document cannot approve it: another authorised user must.
 * This applies to every user, Super Admin included (no permission bypasses it).
 *
 * Work recorded by the system (scheduled jobs, console commands) has no signed-in
 * user and is not checked.
 */
class SeparationOfDuties
{
    public static function assertNotCreator(?int $createdBy, string $what): void
    {
        $user = auth()->id();

        if ($user !== null && $createdBy !== null && (int) $createdBy === (int) $user) {
            throw new DocumentException("You created {$what}, so another user must approve it.");
        }
    }

    /** For views: true when the signed-in user created this document and its type needs approval. */
    public static function blocks(Model&Document $document): bool
    {
        return self::isCreator($document->getAttribute('created_by')) && app(Approvals::class)->required($document->documentType());
    }

    /** For views: true when the signed-in user may not approve because they created it. */
    public static function isCreator(?int $createdBy): bool
    {
        return auth()->id() !== null && $createdBy !== null && (int) $createdBy === (int) auth()->id();
    }
}
