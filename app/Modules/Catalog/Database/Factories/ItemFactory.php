<?php

namespace App\Modules\Catalog\Database\Factories;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('IT-####')),
            'name' => ucfirst($this->faker->words(2, true)),
            'type' => Item::RAW_MATERIAL,
            'stock_unit_id' => fn () => Unit::query()->where('symbol', 'pcs')->value('id') ?? Unit::query()->create(['name' => 'Piece', 'symbol' => 'pcs', 'allows_decimal' => false])->id,
            'can_buy' => true,
            'is_active' => true,
        ];
    }

    public function finished(): static
    {
        return $this->state(['type' => Item::FINISHED_GOOD, 'can_make' => true, 'can_sell' => true, 'can_buy' => false]);
    }

    public function rawMaterial(string $unitSymbol = 'pcs'): static
    {
        return $this->state(['type' => Item::RAW_MATERIAL, 'can_buy' => true, 'stock_unit_id' => fn () => Unit::query()->where('symbol', $unitSymbol)->value('id')]);
    }
}
