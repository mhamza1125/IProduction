<?php

namespace Tests\Fixtures;

use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Minimal document used to test the posting framework.
 */
class TestDocument extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['document_date' => 'date', 'posted_at' => 'datetime', 'approved_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function documentType(): string
    {
        return 'test_document';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->document_date;
    }

    public function permissionKey(): string
    {
        return 'test_documents';
    }

    public function mutableWhenLocked(): array
    {
        return ['effects_run'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['title'])->logOnlyDirty()->dontSubmitEmptyLogs();
    }
}
