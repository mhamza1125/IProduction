<?php

namespace App\Modules\Opening\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $opening_batch_id
 * @property int $row_number
 * @property array<string, string|null> $data
 * @property list<string>|null $errors
 */
class OpeningBatchRow extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['data' => 'array', 'errors' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function result(): MorphTo
    {
        return $this->morphTo();
    }
}
