<?php

namespace App\Modules\Opening\Models;

use App\Modules\Opening\Services\OpeningTypes;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $type
 * @property Carbon $batch_date
 * @property string $file_name
 * @property string $file_hash
 * @property int $row_count
 * @property int $error_count
 * @property array<string, string>|null $control_totals
 * @property array<string, string>|null $totals
 * @property string|null $notes
 */
class OpeningBatch extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'type', 'batch_date', 'file_name', 'file_hash', 'row_count', 'error_count', 'control_totals', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['batch_date' => 'date', 'control_totals' => 'array', 'totals' => 'array', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['totals', 'control_totals'];
    }

    public function documentType(): string
    {
        return 'opening_batch';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->batch_date;
    }

    public function permissionKey(): string
    {
        return 'opening';
    }

    public function showUrl(): string
    {
        return route('opening.show', $this);
    }

    public function typeLabel(): string
    {
        return app(OpeningTypes::class)->get($this->type)->label();
    }

    /** @return HasMany<OpeningBatchRow, $this> */
    public function rows(): HasMany
    {
        return $this->hasMany(OpeningBatchRow::class)->orderBy('row_number');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'type', 'batch_date', 'row_count', 'error_count', 'control_totals', 'totals'])->logOnlyDirty()->useLogName('documents');
    }
}
