<?php

namespace App\Modules\Delivery\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $credit_note_id
 * @property string $description
 * @property string $amount
 * @property string|null $tax_rate
 * @property string $tax_amount
 */
class CreditNoteLine extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'tax_rate' => 'decimal:2', 'tax_amount' => 'decimal:2'];
    }
}
