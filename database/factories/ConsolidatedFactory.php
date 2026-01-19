<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConsolidatedFactory extends Factory
{
    protected $model = Consolidated::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(8, 1, 1000);
        $price = $this->faker->randomFloat(8, 10, 100);

        return [
            'account_id' => Account::factory(),
            'company_ticker_id' => CompanyTicker::factory(),
            'average_purchase_price' => $price,
            'quantity_current' => $quantity,
            'total_purchased' => $quantity * $price,
            'closed' => false,
            'quantity_purchased' => $quantity,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed' => true,
            'quantity_current' => 0,
            'average_selling_price' => $this->faker->randomFloat(8, 10, 150),
            'total_sold' => ($attributes['total_purchased'] ?? 0) * 1.1,
            'quantity_sold' => $attributes['quantity_purchased'] ?? 0,
        ]);
    }

    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_id' => $account->id,
        ]);
    }

    public function forTicker(CompanyTicker $ticker): static
    {
        return $this->state(fn (array $attributes) => [
            'company_ticker_id' => $ticker->id,
        ]);
    }
}
