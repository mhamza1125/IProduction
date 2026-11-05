<?php

namespace App\Modules\Core\Http;

use App\Modules\Core\Models\Attachment;
use App\Support\Models\PermissionScoped;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController
{
    public function __invoke(Attachment $attachment): StreamedResponse
    {
        $parent = $attachment->attachable;

        abort_unless($parent instanceof PermissionScoped, 404);
        abort_unless(auth()->user()?->can($parent->permissionKey().'.view'), 403);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
