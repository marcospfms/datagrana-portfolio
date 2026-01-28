<?php

namespace Tests\Feature\Portfolio;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Composition;
use App\Models\CompositionHistory;
use App\Models\Consolidated;
use App\Models\Portfolio;
use App\Models\Treasure;
use App\Models\TreasureCategory;
use Tests\TestCase;

class CrossingTest extends TestCase
{
    private function enableFullCrossing(array $auth): void
    {
        $subscription = app(\App\Services\SubscriptionLimitService::class)
            ->ensureUserHasSubscription($auth['user']);

        $features = $subscription->features_snapshot ?? [];
        $features['allow_full_crossing'] = true;
        $subscription->update(['features_snapshot' => $features]);
    }

    public function test_can_get_crossing_data(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->enableFullCrossing($auth);
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create(['reference' => 'Acoes']);
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
            'last_price' => 35.00,
        ]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
        ]);

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'quantity_current' => 50,
            'average_purchase_price' => 30.00,
            'total_purchased' => 1500.00,
            'closed' => false,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'portfolio',
                    'crossing' => [
                        '*' => [
                            'ticker',
                            'name',
                            'category',
                            'ideal_percentage',
                            'balance',
                            'to_buy_quantity',
                            'status',
                        ],
                    ],
                ],
            ]);

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('PETR4', $crossingItem['ticker']);
        $this->assertEquals('positioned', $crossingItem['status']);
    }

    public function test_masks_crossing_when_full_access_is_disabled(): void
    {
        $auth = $this->createAuthenticatedUser();
        $subscription = $auth['user']->activeSubscription()->first();
        $subscription->update([
            'features_snapshot' => array_merge($subscription->features_snapshot ?? [], [
                'allow_full_crossing' => false,
            ]),
        ]);

        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create(['reference' => 'Acoes']);
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
            'last_price' => 35.00,
        ]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
        ]);

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'quantity_current' => 50,
            'average_purchase_price' => 30.00,
            'total_purchased' => 1500.00,
            'closed' => false,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('locked', $crossingItem['current_quantity']);
        $this->assertEquals('locked', $crossingItem['to_buy_quantity']);
        $this->assertEquals('locked', $crossingItem['to_buy_quantity_formatted']);
        $this->assertEquals('locked', $crossingItem['profit']);
        $this->assertEquals('locked', $crossingItem['profit_percentage']);

        $summary = $response->json('data.summary');
        $this->assertEquals('locked', $summary['resultValue']);
        $this->assertEquals('locked', $summary['positionedAssets']);
        $this->assertEquals('locked', $summary['notPositionedAssets']);
        $this->assertEquals('locked', $summary['unwindAssets']);
        $this->assertEquals('locked', $summary['avgProfitPercentage']);
        $this->assertEquals('locked', $summary['profitableAssets']);
        $this->assertEquals('locked', $summary['lossAssets']);
        $this->assertEquals('locked', $summary['perfectlyPositioned']);
        $this->assertEquals('locked', $summary['totalProfit']);
    }

    public function test_calculates_to_buy_quantity_correctly(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->enableFullCrossing($auth);
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'last_price' => 35.00,
        ]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
        ]);

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'quantity_current' => 50,
            'average_purchase_price' => 30.00,
            'total_purchased' => 1500.00,
            'closed' => false,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals(21, $crossingItem['to_buy_quantity']);
        $this->assertEquals('21 cotas', $crossingItem['to_buy_quantity_formatted']);
    }

    public function test_identifies_not_positioned_assets(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->enableFullCrossing($auth);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create(['company_id' => $company->id]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('not_positioned', $crossingItem['status']);
        $this->assertEquals(0, $crossingItem['current_quantity']);
    }

    public function test_identifies_unwind_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->enableFullCrossing($auth);
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create(['company_id' => $company->id]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        CompositionHistory::factory()->create([
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $ticker->id,
            'percentage' => 15.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'quantity_current' => 100,
            'closed' => false,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('unwind_position', $crossingItem['status']);
        $this->assertEquals(15.00, $crossingItem['ideal_percentage']);
    }

    public function test_returns_null_to_buy_when_no_price(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->enableFullCrossing($auth);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'last_price' => null,
        ]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        Composition::factory()->forPortfolio($portfolio)->create([
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertNull($crossingItem['to_buy_quantity']);
        $this->assertNull($crossingItem['to_buy_quantity_formatted']);
    }

    public function test_crossing_includes_treasures(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->enableFullCrossing($auth);
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $treasureCategory = TreasureCategory::factory()->create(['reference' => 'Tesouro']);
        $treasure = Treasure::factory()->create(['treasure_category_id' => $treasureCategory->id]);

        $portfolio = Portfolio::factory()->forUser($auth['user'])->create([
            'target_value' => 10000.00,
        ]);

        Composition::factory()->forPortfolio($portfolio)->forTreasure($treasure)->create([
            'percentage' => 10.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'treasure_id' => $treasure->id,
            'quantity_current' => 2,
            'average_purchase_price' => 1000.00,
            'total_purchased' => 2000.00,
            'closed' => false,
        ]);

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $crossingItem = $response->json('data.crossing.0');
        $this->assertEquals('treasure', $crossingItem['type']);
        $this->assertEquals(10.00, $crossingItem['ideal_percentage']);
    }

    public function test_cannot_get_crossing_for_other_user_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->create();

        $response = $this->getJson(
            "/api/portfolios/{$portfolio->id}/crossing",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }

    public function test_cannot_get_crossing_without_authentication(): void
    {
        $portfolio = Portfolio::factory()->create();

        $response = $this->getJson("/api/portfolios/{$portfolio->id}/crossing");

        $response->assertStatus(401);
    }
}
