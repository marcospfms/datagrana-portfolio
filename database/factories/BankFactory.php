<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class BankFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Corretora',
            'nickname' => fake()->word(),
            'cnpj' => fake()->numerify('##.###.###/####-##'),
            'photo' => fake()->imageUrl(100, 100),
            'status' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
