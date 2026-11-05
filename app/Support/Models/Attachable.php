<?php

namespace App\Support\Models;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A record that can have attachments and notes. Use the HasAttachmentsAndNotes trait.
 */
interface Attachable extends PermissionScoped
{
    /** @return MorphMany<Attachment, covariant Model> */
    public function attachments(): MorphMany;

    /** @return MorphMany<Note, covariant Model> */
    public function notes(): MorphMany;
}
