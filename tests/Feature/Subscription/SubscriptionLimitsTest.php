<?php

namespace Tests\Feature\Subscription;

use App\Models\Account;
use App\Models\Composition;
use App\Models\Portfolio;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Tests\TestCase;

class SubscriptionLimitsTest extends TestCase
{
    public function test_user_receives_free_subscription_on_create(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_slug' => 'free',
        ]);

        $this->assertDatabaseHas('user_subscription_usage', [
            'user_id' => $user->id,
        ]);
    }

    public function test_free_plan_blocks_second_account_creation(): void
    {
        $user = User::factory()->create();

        Account::factory()->create(['user_id' => $user->id]);

        $service = app(SubscriptionLimitService::class);

        $this->assertFalse($service->canCreateAccount($user));
    }

    public function test_free_plan_has_limited_crossing_access(): void
    {
        $user = User::factory()->create();

        $service = app(SubscriptionLimitService::class);

        $this->assertFalse($service->hasFullCrossingAccess($user));
    }

    public function test_composition_limits_apply_per_portfolio(): void
    {
        $user = User::factory()->create();
        $service = app(SubscriptionLimitService::class);

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $service->createSubscriptionFromPlan($user, $plan);

        $portfolioA = Portfolio::factory()->create(['user_id' => $user->id]);
        $portfolioB = Portfolio::factory()->create(['user_id' => $user->id]);

        Composition::factory()
            ->count($plan->getLimit('max_compositions'))
            ->forPortfolio($portfolioA)
            ->create();

        $this->assertFalse($service->canAddComposition($user, $portfolioA));
        $this->assertTrue($service->canAddComposition($user, $portfolioB));
    }
}
