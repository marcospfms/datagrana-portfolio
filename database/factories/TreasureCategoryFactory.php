<?php

namespace Database\Factories;

use App\Models\Coin;
use App\Models\TreasureCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasureCategoryFactory extends Factory
{
    protected $model = TreasureCategory::class;

    public function definition(): array
    {
        return [
            'coin_id' => Coin::factory(),
            'name' => $this->faker->unique()->words(2, true),
            'short_name' => $this->faker->word(),
            'reference' => strtoupper($this->faker->lexify('???')),
            'list_updated_at' => now(),
            'can_set_net_balance' => false,
            'color_hex' => $this->faker->hexColor(),
            'icon' => $this->faker->word(),
        ];
    }
}
