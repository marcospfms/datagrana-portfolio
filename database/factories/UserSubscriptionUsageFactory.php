<?php

namespace Database\Factories;

use App\Models\UserSubscriptionUsage;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSubscriptionUsageFactory extends Factory
{
    protected $model = UserSubscriptionUsage::class;

    public function definition(): array
    {
        return [
            'current_portfolios' => 0,
            'current_compositions' => 0,
            'current_positions' => 0,
            'current_accounts' => 0,
            'last_calculated_at' => now(),
        ];
    }
}
