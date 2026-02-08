<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use App\Models\Earning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_own_earnings(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $tickerA = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'AAAA3',
        ]);
        $tickerB = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'BBBB4',
        ]);
        $consolidatedA = Consolidated::factory()->forAccount($account)->forTicker($tickerA)->create();
        $consolidatedB = Consolidated::factory()->forAccount($account)->forTicker($tickerB)->create();

        Earning::factory()->forConsolidated($consolidatedA)->create(['date' => '2026-02-05']);
        Earning::factory()->forConsolidated($consolidatedB)->create(['date' => '2026-02-05']);
        Earning::factory()->forConsolidated($consolidatedA)->create(['date' => '2026-02-01']);
        Earning::factory()->count(1)->create();

        $response = $this->getJson('/api/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.date', '2026-02-05')
            ->assertJsonPath('data.0.data.0.consolidated.company_ticker.code', 'AAAA3')
            ->assertJsonPath('data.0.data.1.consolidated.company_ticker.code', 'BBBB4');
    }

    public function test_cannot_list_earnings_without_authentication(): void
    {
        $response = $this->getJson('/api/earnings');

        $response->assertStatus(401);
    }
}
