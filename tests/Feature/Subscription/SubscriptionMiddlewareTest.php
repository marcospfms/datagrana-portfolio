<?php

namespace Tests\Feature\Subscription;

use App\Models\Account;
use App\Models\CompanyTicker;
use App\Models\Portfolio;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Models\UserSubscriptionUsage;
use App\Services\SubscriptionLimitService;
use Tests\TestCase;

class SubscriptionMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['subscription.enforce_limits' => true]);
    }

    public function test_middleware_blocks_portfolio_creation_when_limit_reached(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        Portfolio::factory()
            ->count($freePlan->getLimit('max_portfolios'))
            ->create(['user_id' => $user->id]);
        $subscription = app(SubscriptionLimitService::class)
            ->createSubscriptionFromPlan($user, $freePlan);
        $subscription->usage?->recalculate();

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Nova Carteira',
            'target_value' => 10000,
            'month_value' => 1000,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SUBSCRIPTION_LIMIT_EXCEEDED',
            ]);
    }

    public function test_middleware_allows_portfolio_creation_when_under_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        app(SubscriptionLimitService::class)->createSubscriptionFromPlan($user, $freePlan);

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Nova Carteira',
            'target_value' => 10000,
            'month_value' => 1000,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
    }

    public function test_middleware_blocks_account_creation_when_limit_reached(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        Account::factory()
            ->count($freePlan->getLimit('max_accounts'))
            ->create(['user_id' => $user->id]);
        $subscription = app(SubscriptionLimitService::class)
            ->createSubscriptionFromPlan($user, $freePlan);
        $subscription->usage?->recalculate();

        $response = $this->postJson('/api/accounts', [
            'account' => '12345',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SUBSCRIPTION_LIMIT_EXCEEDED',
            ]);
    }

    public function test_middleware_skips_when_enforce_limits_is_disabled(): void
    {
        config(['subscription.enforce_limits' => false]);

        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        app(SubscriptionLimitService::class)->createSubscriptionFromPlan($user, $freePlan);

        $response = $this->postJson('/api/portfolios', [
            'name' => 'Nova Carteira',
            'target_value' => 10000,
            'month_value' => 1000,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
    }

    public function test_middleware_blocks_composition_creation_when_limit_reached(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        app(SubscriptionLimitService::class)->createSubscriptionFromPlan($user, $freePlan);

        $portfolio = Portfolio::factory()->forUser($user)->create();

        for ($i = 0; $i < $freePlan->getLimit('max_compositions'); $i++) {
            \App\Models\Composition::factory()->forPortfolio($portfolio)->create();
        }

        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'type' => 'company',
                    'asset_id' => $ticker->id,
                    'percentage' => 10,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'error_code' => 'SUBSCRIPTION_LIMIT_EXCEEDED',
            ]);
    }
}
