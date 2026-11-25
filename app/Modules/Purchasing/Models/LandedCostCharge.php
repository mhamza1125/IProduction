<?php

namespace App\Modules\Purchasing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $landed_cost_id
 * @property string $charge_type
 * @property string|null $description
 * @property string $amount
 */
class LandedCostCharge extends Model
{
    protected $fillable = ['landed_cost_id', 'charge_type', 'description', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }
}
