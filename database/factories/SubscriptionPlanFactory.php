<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPlanFactory extends Factory
{
    protected $model = SubscriptionPlan::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'slug' => $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'price_monthly' => $this->faker->randomFloat(2, 0, 100),
            'is_active' => true,
            'display_order' => $this->faker->numberBetween(1, 10),
            'revenuecat_product_id' => 'test_product_' . $this->faker->unique()->word,
            'revenuecat_entitlement_id' => 'test_entitlement_' . $this->faker->word,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Gratuito',
            'slug' => 'free',
            'price_monthly' => 0.00,
            'display_order' => 1,
            'revenuecat_product_id' => null,
            'revenuecat_entitlement_id' => null,
        ]);
    }

    public function starter(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Investidor Iniciante',
            'slug' => 'starter',
            'price_monthly' => 19.90,
            'display_order' => 2,
            'revenuecat_product_id' => 'datagrana_starter_monthly',
            'revenuecat_entitlement_id' => 'starter',
        ]);
    }

    public function pro(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Investidor Pro',
            'slug' => 'pro',
            'price_monthly' => 39.90,
            'display_order' => 3,
            'revenuecat_product_id' => 'datagrana_pro_monthly',
            'revenuecat_entitlement_id' => 'pro',
        ]);
    }

    public function premium(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Premium',
            'slug' => 'premium',
            'price_monthly' => 79.90,
            'display_order' => 4,
            'revenuecat_product_id' => 'datagrana_premium_monthly',
            'revenuecat_entitlement_id' => 'premium',
        ]);
    }
}
