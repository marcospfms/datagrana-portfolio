<?php

namespace Tests\Feature\Subscription;

use App\Models\Composition;
use App\Models\Portfolio;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionResourceLocksTest extends TestCase
{
    use RefreshDatabase;

    public function test_portfolio_resource_shows_is_locked_true_when_over_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];
        $service = app(SubscriptionLimitService::class);

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $service->createSubscriptionFromPlan($user, $plan);

        $maxPortfolios = $plan->getLimit('max_portfolios');

        Portfolio::factory()
            ->count($maxPortfolios + 2)
            ->for($user)
            ->create();

        $response = $this->getJson('/api/portfolios', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $data = $response->json('data');
        $lockedCount = 0;
        $unlockedCount = 0;

        foreach ($data as $portfolio) {
            if ($portfolio['is_locked']) {
                $lockedCount++;
            } else {
                $unlockedCount++;
            }
        }

        $this->assertEquals($maxPortfolios, $unlockedCount, 'Should have exactly max_portfolios unlocked');
        $this->assertEquals(2, $lockedCount, 'Should have 2 locked portfolios');
    }

    public function test_portfolio_resource_shows_is_locked_false_when_under_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Plano free permite 1 portfolio
        Portfolio::factory()->for($user)->create();

        $response = $this->getJson('/api/portfolios', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $data = $response->json('data');

        foreach ($data as $portfolio) {
            $this->assertFalse($portfolio['is_locked'], 'Portfolio should be unlocked when under limit');
        }
    }

    public function test_composition_resource_shows_is_locked_true_when_over_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];
        $service = app(SubscriptionLimitService::class);

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $service->createSubscriptionFromPlan($user, $plan);

        $portfolio = Portfolio::factory()->for($user)->create();

        $maxCompositions = $plan->getLimit('max_compositions');

        Composition::factory()
            ->count($maxCompositions + 3)
            ->forPortfolio($portfolio)
            ->create();

        $response = $this->getJson("/api/portfolios/{$portfolio->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $compositions = $response->json('data.compositions');
        $lockedCount = 0;
        $unlockedCount = 0;

        foreach ($compositions as $composition) {
            if ($composition['is_locked']) {
                $lockedCount++;
            } else {
                $unlockedCount++;
            }
        }

        $this->assertEquals($maxCompositions, $unlockedCount, 'Should have exactly max_compositions unlocked');
        $this->assertEquals(3, $lockedCount, 'Should have 3 locked compositions');
    }
}
