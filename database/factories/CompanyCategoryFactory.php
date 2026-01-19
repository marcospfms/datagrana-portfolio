<?php

namespace Database\Factories;

use App\Models\Coin;
use App\Models\CompanyCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyCategoryFactory extends Factory
{
    protected $model = CompanyCategory::class;

    public function definition(): array
    {
        $references = ['Acoes', 'FII', 'ETF', 'BDR'];

        return [
            'coin_id' => Coin::factory(),
            'name' => $this->faker->unique()->company() . ' Category',
            'short_name' => $this->faker->word(),
            'reference' => $this->faker->randomElement($references),
            'status' => true,
            'color_hex' => $this->faker->hexColor(),
            'icon' => $this->faker->word(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function acoes(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Acoes',
            'short_name' => 'Acoes',
            'reference' => 'Acoes',
        ]);
    }

    public function fii(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Fundos Imobiliarios',
            'short_name' => 'FIIs',
            'reference' => 'FII',
        ]);
    }
}
