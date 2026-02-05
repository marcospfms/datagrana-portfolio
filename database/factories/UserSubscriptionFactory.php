<?php

namespace Database\Factories;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserSubscriptionFactory extends Factory
{
    protected $model = UserSubscription::class;

    public function definition(): array
    {
        $plan = SubscriptionPlan::where('slug', 'starter')->first()
            ?? SubscriptionPlan::first();

        return [
            'user_id' => User::factory(),
            'subscription_plan_id' => $plan?->id ?? 1,
            'plan_name' => $plan?->name ?? 'Investidor Iniciante',
            'plan_slug' => $plan?->slug ?? 'starter',
            'price_monthly' => $plan?->price_monthly ?? 19.90,
            'limits_snapshot' => [
                'max_portfolios' => 2,
                'max_compositions' => 25,
                'max_positions' => 25,
                'max_accounts' => 2,
            ],
            'features_snapshot' => [
                'allow_full_crossing' => true,
                'allow_composition_history' => true,
            ],
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'renews_at' => now()->addMonth(),
            'is_paid' => true,
            'paid_at' => now(),
            'payment_method' => 'PLAY_STORE',
            'renewal_count' => 0,
        ];
    }

    public function free(): static
    {
        return $this->state(function (array $attributes) {
            $plan = SubscriptionPlan::where('slug', 'free')->first();

            return [
                'subscription_plan_id' => $plan?->id ?? 1,
                'plan_name' => 'Gratuito',
                'plan_slug' => 'free',
                'price_monthly' => 0.00,
                'limits_snapshot' => [
                    'max_portfolios' => 1,
                    'max_compositions' => 10,
                    'max_positions' => 10,
                    'max_accounts' => 1,
                ],
                'features_snapshot' => [
                    'allow_full_crossing' => false,
                    'allow_composition_history' => false,
                ],
                'ends_at' => null,
                'renews_at' => null,
                'is_paid' => false,
                'paid_at' => null,
                'payment_method' => null,
            ];
        });
    }

    public function starter(): static
    {
        return $this->state(function (array $attributes) {
            $plan = SubscriptionPlan::where('slug', 'starter')->first();

            return [
                'subscription_plan_id' => $plan?->id,
                'plan_name' => 'Investidor Iniciante',
                'plan_slug' => 'starter',
                'price_monthly' => 19.90,
                'limits_snapshot' => [
                    'max_portfolios' => 2,
                    'max_compositions' => 25,
                    'max_positions' => 25,
                    'max_accounts' => 2,
                ],
                'features_snapshot' => [
                    'allow_full_crossing' => true,
                    'allow_composition_history' => true,
                ],
            ];
        });
    }

    public function pro(): static
    {
        return $this->state(function (array $attributes) {
            $plan = SubscriptionPlan::where('slug', 'pro')->first();

            return [
                'subscription_plan_id' => $plan?->id,
                'plan_name' => 'Investidor Pro',
                'plan_slug' => 'pro',
                'price_monthly' => 39.90,
                'limits_snapshot' => [
                    'max_portfolios' => 4,
                    'max_compositions' => 50,
                    'max_positions' => 50,
                    'max_accounts' => 4,
                ],
                'features_snapshot' => [
                    'allow_full_crossing' => true,
                    'allow_composition_history' => true,
                ],
            ];
        });
    }

    public function premium(): static
    {
        return $this->state(function (array $attributes) {
            $plan = SubscriptionPlan::where('slug', 'premium')->first();

            return [
                'subscription_plan_id' => $plan?->id,
                'plan_name' => 'Premium',
                'plan_slug' => 'premium',
                'price_monthly' => 79.90,
                'limits_snapshot' => [
                    'max_portfolios' => null,
                    'max_compositions' => null,
                    'max_positions' => null,
                    'max_accounts' => null,
                ],
                'features_snapshot' => [
                    'allow_full_crossing' => true,
                    'allow_composition_history' => true,
                ],
            ];
        });
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'expired',
            'ends_at' => now()->subDay(),
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7),
            'is_paid' => false,
            'paid_at' => null,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function withRevenueCat(string $originalTransactionId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'revenuecat_subscriber_id' => 'sub_' . uniqid(),
            'revenuecat_original_transaction_id' => $originalTransactionId ?? 'txn_' . uniqid(),
            'revenuecat_product_id' => 'datagrana_starter_monthly',
            'revenuecat_entitlement_id' => 'starter',
            'revenuecat_store' => 'PLAY_STORE',
        ]);
    }
}
