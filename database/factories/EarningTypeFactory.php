<?php

namespace Database\Factories;

use App\Models\EarningType;
use Illuminate\Database\Eloquent\Factories\Factory;

class EarningTypeFactory extends Factory
{
    protected $model = EarningType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'short_name' => $this->faker->word(),
            'label' => $this->faker->word(),
            'key' => strtoupper($this->faker->unique()->lexify('???')),
            'icon' => $this->faker->word(),
            'hex_color' => $this->faker->hexColor(),
        ];
    }
}
