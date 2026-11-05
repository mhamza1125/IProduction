<?php

namespace App\Support\Models;

use App\Modules\Core\Models\Attachment;
use App\Modules\Core\Models\Note;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Adds file attachments and notes to a model. The model must implement PermissionScoped.
 * Show them with <livewire:core::shared.attachments :model="$record" /> and
 * <livewire:core::shared.notes :model="$record" />.
 */
trait HasAttachmentsAndNotes
{
    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest('id');
    }

    /** @return MorphMany<Note, $this> */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'notable')->latest('id');
    }
}
