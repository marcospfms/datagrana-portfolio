<?php

namespace Tests\Feature\Portfolio;

use App\Models\CompanyTicker;
use App\Models\Portfolio;
use App\Models\Treasure;
use Tests\TestCase;

class CompositionStoreTest extends TestCase
{
    public function test_can_add_company_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'type' => 'company',
                    'asset_id' => $ticker->id,
                    'percentage' => 25.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Composicoes adicionadas com sucesso.',
            ]);

        $this->assertDatabaseHas('compositions', [
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $ticker->id,
            'percentage' => 25.00,
        ]);
    }

    public function test_can_add_treasure_composition(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $treasure = Treasure::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'type' => 'treasure',
                    'asset_id' => $treasure->id,
                    'percentage' => 10.50,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $this->assertDatabaseHas('compositions', [
            'portfolio_id' => $portfolio->id,
            'treasure_id' => $treasure->id,
            'percentage' => 10.50,
        ]);
    }

    public function test_can_add_single_company_with_shorthand_payload(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'company_ticker_id' => $ticker->id,
            'percentage' => 33.3,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Composicoes adicionadas com sucesso.',
            ]);

        $this->assertDatabaseHas('compositions', [
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $ticker->id,
            'percentage' => 33.3,
        ]);
    }

    public function test_can_add_multiple_companies_with_ticker_percentage_map(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $tickerA = CompanyTicker::factory()->create(['code' => 'ABCD3']);
        $tickerB = CompanyTicker::factory()->create(['code' => 'WXYZ4']);

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'ticker_percentages' => [
                'ABCD3' => 40,
                'WXYZ4' => 60,
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Composicoes adicionadas com sucesso.',
            ]);

        $this->assertDatabaseHas('compositions', [
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $tickerA->id,
            'percentage' => 40,
        ]);

        $this->assertDatabaseHas('compositions', [
            'portfolio_id' => $portfolio->id,
            'company_ticker_id' => $tickerB->id,
            'percentage' => 60,
        ]);
    }

    public function test_cannot_add_to_other_user_portfolio(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'type' => 'company',
                    'asset_id' => $ticker->id,
                    'percentage' => 25.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    public function test_percentage_must_be_valid(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();
        $ticker = CompanyTicker::factory()->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'type' => 'company',
                    'asset_id' => $ticker->id,
                    'percentage' => 150.00,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['compositions.0.percentage']);
    }

    public function test_asset_must_exist_and_be_active(): void
    {
        $auth = $this->createAuthenticatedUser();
        $portfolio = Portfolio::factory()->forUser($auth['user'])->create();

        $response = $this->postJson("/api/portfolios/{$portfolio->id}/compositions", [
            'compositions' => [
                [
                    'type' => 'company',
                    'asset_id' => 999999,
                    'percentage' => 10,
                ],
            ],
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['compositions.0.asset_id']);
    }
}
