<?php

namespace Tests\Feature\Portfolio;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\CompositionHistory;
use App\Models\Portfolio;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_portfolio_history_grouped_by_date(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->grantHistoryFeature($auth['user']->id);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        $tickerA = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'HIST3',
        ]);
        $tickerB = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'HIST4',
        ]);

        CompositionHistory::factory()->forPortfolio($portfolio)->forCompanyTicker($tickerA)->create([
            'created_at' => '2026-02-01 10:00:00',
            'deleted_at' => '2026-02-11 10:00:00',
        ]);
        CompositionHistory::factory()->forPortfolio($portfolio)->forCompanyTicker($tickerB)->create([
            'created_at' => '2026-02-02 10:00:00',
            'deleted_at' => '2026-02-11 11:00:00',
        ]);
        CompositionHistory::factory()->forPortfolio($portfolio)->forCompanyTicker($tickerA)->create([
            'created_at' => '2026-02-03 10:00:00',
            'deleted_at' => '2026-02-09 10:00:00',
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/history",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total_events', 3)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.date', '2026-02-11')
            ->assertJsonPath('data.data.0.data.0.company_ticker.code', 'HIST3')
            ->assertJsonPath('data.data.0.data.1.company_ticker.code', 'HIST4');
    }

    public function test_can_filter_portfolio_history_by_ticker_search(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->grantHistoryFeature($auth['user']->id);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        $tickerA = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'ABCD3',
        ]);
        $tickerB = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'WXYZ4',
        ]);

        CompositionHistory::factory()->forPortfolio($portfolio)->forCompanyTicker($tickerA)->create();
        CompositionHistory::factory()->forPortfolio($portfolio)->forCompanyTicker($tickerB)->create();

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/history?search=ABCD",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.meta.total_events', 1)
            ->assertJsonPath('data.data.0.data.0.company_ticker.code', 'ABCD3');
    }

    public function test_history_validates_search_param(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->grantHistoryFeature($auth['user']->id);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/history?search=" . str_repeat('A', 101),
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_cannot_list_other_user_portfolio_history(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->grantHistoryFeature($auth['user']->id);

        $otherPortfolio = Portfolio::factory()->create();

        $response = $this->getJson(
            "/api/portfolios/{$otherPortfolio->id}/history",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }

    public function test_cannot_list_portfolio_history_without_authentication(): void
    {
        $portfolio = Portfolio::factory()->create();

        $response = $this->getJson("/api/portfolios/{$portfolio->id}/history");

        $response->assertStatus(401);
    }

    private function grantHistoryFeature(int $userId): void
    {
        $user = User::findOrFail($userId);
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        app(SubscriptionLimitService::class)->createSubscriptionFromPlan($user, $plan);
    }
}
