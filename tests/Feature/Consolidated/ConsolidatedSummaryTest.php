<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_get_summary(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'last_price' => 50.00,
        ]);

        Consolidated::factory()->create([
            'account_id' => $account->id,
            'company_ticker_id' => $ticker->id,
            'average_purchase_price' => 40.00,
            'quantity_current' => 100,
            'total_purchased' => 4000.00,
            'closed' => false,
        ]);

        $response = $this->getJson('/api/consolidated/summary', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_invested',
                    'total_current',
                    'total_profit',
                    'profit_percentage',
                    'assets_count',
                    'by_category',
                    'by_account',
                ],
            ])
            ->assertJsonPath('data.total_invested', 4000)
            ->assertJsonPath('data.total_current', 5000)
            ->assertJsonPath('data.total_profit', 1000)
            ->assertJsonPath('data.assets_count', 1);
    }

    public function test_summary_excludes_closed_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->forAccount($account)->create(['closed' => false]);
        Consolidated::factory()->forAccount($account)->create(['closed' => true]);

        $response = $this->getJson('/api/consolidated/summary', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.assets_count', 1);
    }

    public function test_cannot_get_summary_without_authentication(): void
    {
        $response = $this->getJson('/api/consolidated/summary');

        $response->assertStatus(401);
    }
}
