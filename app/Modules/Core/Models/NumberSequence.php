<?php

namespace App\Modules\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Numbering format for one document type. Counters live in number_sequence_counters.
 *
 * @property int $id
 * @property string $document_type
 * @property string $prefix
 * @property string $separator
 * @property string $year_format
 * @property int $padding
 */
class NumberSequence extends Model
{
    use LogsActivity;

    public const YEAR_FORMATS = ['YY' => 'Two digits (26)', 'YYYY' => 'Four digits (2026)', 'none' => 'No year (never resets)'];

    protected $fillable = ['document_type', 'prefix', 'separator', 'year_format', 'padding'];

    protected function casts(): array
    {
        return ['padding' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
