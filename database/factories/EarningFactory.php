<?php

namespace Database\Factories;

use App\Models\Earning;
use App\Models\EarningType;
use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\Consolidated;

class EarningFactory extends Factory
{
    protected $model = Earning::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(8, 1, 1000);
        $net = $this->faker->randomFloat(8, 1, 500);
        $tax = $this->faker->randomFloat(8, 0, 50);

        return [
            'consolidated_id' => Consolidated::factory(),
            'earning_type_id' => EarningType::factory(),
            'company_earning_id' => null,
            'date' => $this->faker->dateTimeBetween('-6 months', 'now'),
            'quantity' => $quantity,
            'net_value' => $net,
            'gross_value' => $net + $tax,
            'tax' => $tax,
            'imported_with' => 'Manual',
        ];
    }

    public function forConsolidated(Consolidated $consolidated): static
    {
        return $this->state(fn (array $attributes) => [
            'consolidated_id' => $consolidated->id,
        ]);
    }
}
