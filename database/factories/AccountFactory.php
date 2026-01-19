<?php

namespace Database\Factories;

use App\Models\Bank;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_id' => Bank::factory(),
            'account' => fake()->unique()->numerify('######-#'),
            'nickname' => fake()->word(),
            'default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'default' => true,
        ]);
    }

    public function withoutBank(): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_id' => null,
        ]);
    }
}
