<?php

namespace Database\Factories;

use App\Models\CompanyEarning;
use App\Models\CompanyTicker;
use App\Models\EarningType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyEarningFactory extends Factory
{
    protected $model = CompanyEarning::class;

    public function definition(): array
    {
        return [
            'company_ticker_id' => CompanyTicker::factory(),
            'earning_type_id' => EarningType::factory(),
            'origin' => 'sync',
            'status' => true,
            'value' => $this->faker->randomFloat(8, 0.1, 5),
            'approved_date' => now()->subDays(10),
            'payment_date' => now()->subDays(5),
        ];
    }
}
