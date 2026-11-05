<?php

namespace App\Modules\People\Database\Factories;

use App\Modules\People\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Customer> */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return ['name' => $this->faker->company(), 'currency_code' => 'USD', 'country' => 'United States', 'is_active' => true];
    }
}
