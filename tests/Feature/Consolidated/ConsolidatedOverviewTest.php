<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Models\Treasure;
use App\Models\TreasureCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_overview_with_expected_business_metrics(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $stockCategory = CompanyCategory::factory()->create([
            'name' => 'Acoes',
            'reference' => 'ACAO',
        ]);
        $fiiCategory = CompanyCategory::factory()->create([
            'name' => 'Fundos Imobiliarios',
            'reference' => 'FII',
        ]);
        $treasureCategory = TreasureCategory::factory()->create([
            'name' => 'Tesouro Selic',
        ]);

        $stockCompany = Company::factory()->create([
            'company_category_id' => $stockCategory->id,
            'segment' => 'Financeiro',
            'sector' => 'Bancos',
        ]);
        $fiiCompany = Company::factory()->create([
            'company_category_id' => $fiiCategory->id,
            'segment' => 'Logistico',
            'sector' => 'Galpoes',
        ]);

        $stockTicker = CompanyTicker::factory()->create([
            'company_id' => $stockCompany->id,
            'code' => 'STCK3',
            'last_price' => 10.00,
        ]);
        $fiiTicker = CompanyTicker::factory()->create([
            'company_id' => $fiiCompany->id,
            'code' => 'FII11',
            'last_price' => 8.00,
        ]);
        $treasure = Treasure::factory()->create([
            'treasure_category_id' => $treasureCategory->id,
            'last_unit_price' => 120.00,
        ]);

        $openStock = Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $stockTicker->id,
            'treasure_id' => null,
            'quantity_current' => 100,
            'total_purchased' => 800.00,
            'average_purchase_price' => 8.00,
            'closed' => false,
        ]);
        Earning::factory()->forConsolidated($openStock)->create(['net_value' => 50.00]);

        $openFii = Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $fiiTicker->id,
            'treasure_id' => null,
            'quantity_current' => 20,
            'total_purchased' => 200.00,
            'average_purchase_price' => 10.00,
            'closed' => false,
        ]);
        Earning::factory()->forConsolidated($openFii)->create(['net_value' => 20.00]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => null,
            'treasure_id' => $treasure->id,
            'quantity_current' => 5,
            'total_purchased' => 500.00,
            'average_purchase_price' => 100.00,
            'closed' => false,
        ]);

        $closedPosition = Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $stockTicker->id,
            'treasure_id' => null,
            'quantity_current' => 0,
            'total_purchased' => 1000.00,
            'total_sold' => 1200.00,
            'closed' => true,
        ]);
        Earning::factory()->forConsolidated($closedPosition)->create(['net_value' => 50.00]);

        $response = $this->getJson('/api/consolidated/overview', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'active_positions_summary',
                    'capital_gains_summary',
                    'category_allocation',
                    'institution_allocation',
                    'stocks_segment_allocation',
                    'stocks_sector_allocation',
                    'fiis_segment_allocation',
                    'fiis_sector_allocation',
                ],
            ])
            ->assertJsonPath('data.active_positions_summary.total_invested', 1500)
            ->assertJsonPath('data.active_positions_summary.total_current_value', 1760)
            ->assertJsonPath('data.active_positions_summary.total_dividends', 70)
            ->assertJsonPath('data.active_positions_summary.capital_profit_loss', 260)
            ->assertJsonPath('data.active_positions_summary.total_profit', 330)
            ->assertJsonPath('data.active_positions_summary.positions_count', 3)
            ->assertJsonPath('data.capital_gains_summary.total_purchased', 1000)
            ->assertJsonPath('data.capital_gains_summary.total_sold', 1200)
            ->assertJsonPath('data.capital_gains_summary.total_dividends', 50)
            ->assertJsonPath('data.capital_gains_summary.capital_gain_only', 200)
            ->assertJsonPath('data.capital_gains_summary.total_capital_gain', 250)
            ->assertJsonPath('data.capital_gains_summary.positions_count', 1);

        $this->assertSame(
            'Financeiro',
            data_get($response->json('data.stocks_segment_allocation'), '0.name')
        );
        $this->assertSame(
            'Logistico',
            data_get($response->json('data.fiis_segment_allocation'), '0.name')
        );
    }

    public function test_overview_only_uses_authenticated_user_data(): void
    {
        $auth = $this->createAuthenticatedUser();
        $ownAccount = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->forAccount($ownAccount)->create([
            'closed' => false,
            'quantity_current' => 10,
            'average_purchase_price' => 20,
            'total_purchased' => 200,
        ]);

        $otherAuth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create(['user_id' => $otherAuth['user']->id]);
        Consolidated::factory()->forAccount($otherAccount)->create([
            'closed' => false,
            'quantity_current' => 999,
            'average_purchase_price' => 999,
            'total_purchased' => 1,
        ]);

        $response = $this->getJson('/api/consolidated/overview', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.active_positions_summary.positions_count', 1);
    }

    public function test_cannot_get_overview_without_authentication(): void
    {
        $response = $this->getJson('/api/consolidated/overview');

        $response->assertStatus(401);
    }
}
