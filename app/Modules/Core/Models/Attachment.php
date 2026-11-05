<?php

namespace App\Modules\Core\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file attached to any record. Files live on the private "local" disk and
 * are downloaded through AttachmentController, which checks permissions.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size
 * @property int|null $uploaded_by
 */
class Attachment extends Model
{
    /** Allowed upload extensions and the maximum size (KB). */
    public const EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'xls', 'xlsx', 'csv', 'doc', 'docx', 'txt'];

    public const MAX_KB = 10240;

    protected $fillable = ['disk', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'];

    protected static function booted(): void
    {
        static::deleted(fn (Attachment $attachment) => Storage::disk($attachment->disk)->delete($attachment->path));
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function humanSize(): string
    {
        return $this->size >= 1048576
            ? number_format($this->size / 1048576, 1).' MB'
            : number_format(max(1, $this->size / 1024)).' KB';
    }
}
