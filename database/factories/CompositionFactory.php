<?php

namespace Database\Factories;

use App\Models\CompanyTicker;
use App\Models\Composition;
use App\Models\Portfolio;
use App\Models\Treasure;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompositionFactory extends Factory
{
    protected $model = Composition::class;

    public function definition(): array
    {
        return [
            'portfolio_id' => Portfolio::factory(),
            'treasure_id' => null,
            'company_ticker_id' => CompanyTicker::factory(),
            'percentage' => $this->faker->randomFloat(2, 1, 30),
        ];
    }

    public function forPortfolio(Portfolio $portfolio): static
    {
        return $this->state(fn (array $attributes) => [
            'portfolio_id' => $portfolio->id,
        ]);
    }

    public function forTreasure(Treasure $treasure): static
    {
        return $this->state(fn (array $attributes) => [
            'treasure_id' => $treasure->id,
            'company_ticker_id' => null,
        ]);
    }

    public function forCompanyTicker(CompanyTicker $ticker): static
    {
        return $this->state(fn (array $attributes) => [
            'company_ticker_id' => $ticker->id,
            'treasure_id' => null,
        ]);
    }
}
