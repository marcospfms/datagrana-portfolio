<?php

namespace Database\Factories;

use App\Models\Consolidated;
use App\Models\TreasureTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

class TreasureTransactionFactory extends Factory
{
    protected $model = TreasureTransaction::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(8, 1, 100);
        $invested = $this->faker->randomFloat(8, 100, 10000);

        return [
            'consolidated_id' => Consolidated::factory(),
            'date' => now(),
            'operation' => 'C',
            'invested_value' => $invested,
            'quantity' => $quantity,
            'price' => $quantity > 0 ? $invested / $quantity : 0,
            'imported_with' => 'Manual',
        ];
    }
}
