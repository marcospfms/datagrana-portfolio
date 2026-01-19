<?php

namespace Database\Factories;

use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyTransactionFactory extends Factory
{
    protected $model = CompanyTransaction::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(8, 1, 100);
        $price = $this->faker->randomFloat(8, 10, 100);

        return [
            'consolidated_id' => Consolidated::factory(),
            'date' => now(),
            'operation' => 'C',
            'quantity' => $quantity,
            'price' => $price,
            'total_value' => $quantity * $price,
            'imported_with' => 'Manual',
        ];
    }
}
