<?php

namespace App\Modules\People\Database\Factories;

use App\Modules\People\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vendor> */
class VendorFactory extends Factory
{
    protected $model = Vendor::class;

    public function definition(): array
    {
        return ['name' => $this->faker->company(), 'phone' => $this->faker->phoneNumber(), 'is_active' => true];
    }
}
