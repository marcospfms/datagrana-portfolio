<?php

namespace Database\Factories;

use App\Models\Treasure;
use App\Models\TreasureCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasureFactory extends Factory
{
    protected $model = Treasure::class;

    public function definition(): array
    {
        return [
            'treasure_category_id' => TreasureCategory::factory(),
            'name' => $this->faker->unique()->words(3, true),
            'expiration_date' => now()->addYears(5),
            'status' => true,
            'is_overdue' => false,
            'can_buy' => true,
            'can_sell' => true,
            'code' => strtoupper($this->faker->lexify('?????')),
            'last_unit_price' => $this->faker->randomFloat(8, 10, 100),
            'last_unit_price_updated' => now(),
            'imported_with' => 'Manual',
        ];
    }
}
