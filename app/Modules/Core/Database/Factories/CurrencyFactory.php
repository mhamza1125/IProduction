<?php

namespace App\Modules\Core\Database\Factories;

use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Currency>
 */
class CurrencyFactory extends Factory
{
    protected $model = Currency::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('???')),
            'name' => $this->faker->word().' Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
        ];
    }
}
