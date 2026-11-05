<?php

namespace App\Modules\People\Database\Factories;

use App\Modules\People\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'employee_type' => Employee::TYPE_SALARY,
            'join_date' => '2025-01-01',
            'monthly_salary' => '50000.00',
            'is_active' => true,
        ];
    }

    public function wage(): static
    {
        return $this->state(['employee_type' => Employee::TYPE_WAGE, 'monthly_salary' => null]);
    }
}
