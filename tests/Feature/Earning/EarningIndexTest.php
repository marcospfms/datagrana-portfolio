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
            ->assertJsonCount(2, 'data.data')
            ->assertJsonPath('data.data.0.date', '2026-02-05')
            ->assertJsonPath('data.data.0.data.0.consolidated.company_ticker.code', 'AAAA3')
            ->assertJsonPath('data.data.0.data.1.consolidated.company_ticker.code', 'BBBB4')
            ->assertJsonPath('data.meta.per_page', 5);
    }

    public function test_earnings_are_paginated_by_date_groups_with_five_dates_per_page(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PAGI3',
        ]);
        $consolidated = Consolidated::factory()->forAccount($account)->forTicker($ticker)->create();

        Earning::factory()->forConsolidated($consolidated)->create(['date' => '2026-02-06']);
        Earning::factory()->forConsolidated($consolidated)->create(['date' => '2026-02-05']);
        Earning::factory()->forConsolidated($consolidated)->create(['date' => '2026-02-04']);
        Earning::factory()->forConsolidated($consolidated)->create(['date' => '2026-02-03']);
        Earning::factory()->forConsolidated($consolidated)->create(['date' => '2026-02-02']);
        Earning::factory()->forConsolidated($consolidated)->create(['date' => '2026-02-01']);

        $firstPage = $this->getJson('/api/earnings?page=1', $this->authHeaders($auth['token']));
        $firstPage->assertStatus(200)
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total', 6)
            ->assertJsonCount(5, 'data.data')
            ->assertJsonPath('data.data.0.date', '2026-02-06')
            ->assertJsonPath('data.data.4.date', '2026-02-02');

        $secondPage = $this->getJson('/api/earnings?page=2', $this->authHeaders($auth['token']));
        $secondPage->assertStatus(200)
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.date', '2026-02-01');
    }

    public function test_cannot_list_earnings_without_authentication(): void
    {
        $response = $this->getJson('/api/earnings');

        $response->assertStatus(401);
    }
}
