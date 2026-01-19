<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\CompanyTicker;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyTickerFactory extends Factory
{
    protected $model = CompanyTicker::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'code' => strtoupper($this->faker->unique()->lexify('????')) . $this->faker->randomNumber(1),
            'trade_code' => 'BVMF',
            'status' => true,
            'can_update' => true,
            'last_price' => $this->faker->randomFloat(2, 10, 100),
            'last_price_updated' => now(),
            'last_earnings_updated' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function withoutPrice(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_price' => null,
            'last_price_updated' => null,
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }
}
