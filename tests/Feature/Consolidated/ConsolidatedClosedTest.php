<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_only_closed_positions_from_authenticated_user(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->for($auth['user'])->create();

        Consolidated::factory()->forAccount($account)->closed()->create();
        Consolidated::factory()->forAccount($account)->create(['closed' => false]);

        $otherAccount = Account::factory()->create();
        Consolidated::factory()->forAccount($otherAccount)->closed()->create();

        $response = $this->getJson('/api/consolidated/closed', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.closed', true)
            ->assertJsonPath('meta.total_closed', 1);
    }

    public function test_can_filter_closed_positions_by_search(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->for($auth['user'])->create();

        $category = CompanyCategory::factory()->create();
        $companyA = Company::factory()->create(['company_category_id' => $category->id]);
        $companyB = Company::factory()->create(['company_category_id' => $category->id]);

        $tickerA = CompanyTicker::factory()->create([
            'company_id' => $companyA->id,
            'code' => 'ABCD3',
        ]);
        $tickerB = CompanyTicker::factory()->create([
            'company_id' => $companyB->id,
            'code' => 'WXYZ4',
        ]);

        Consolidated::factory()->forAccount($account)->forTicker($tickerA)->closed()->create();
        Consolidated::factory()->forAccount($account)->forTicker($tickerB)->closed()->create();

        $response = $this->getJson('/api/consolidated/closed?search=ABCD', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_ticker.code', 'ABCD3')
            ->assertJsonPath('meta.total_closed', 2);
    }

    public function test_closed_endpoint_validates_query_params(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson(
            '/api/consolidated/closed?search=' . str_repeat('A', 101),
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_closed_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/consolidated/closed');

        $response->assertStatus(401);
    }
}
