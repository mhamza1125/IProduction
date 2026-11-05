<?php

namespace App\Modules\People\Database\Factories;

use App\Modules\People\Models\Contractor;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contractor> */
class ContractorFactory extends Factory
{
    protected $model = Contractor::class;

    public function definition(): array
    {
        return ['name' => $this->faker->name(), 'work_types' => ['Stitching'], 'is_active' => true];
    }
}
