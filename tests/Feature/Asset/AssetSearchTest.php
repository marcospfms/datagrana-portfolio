<?php

namespace Tests\Feature\Asset;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_search_assets_by_ticker_code(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'VALE3',
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=PETR', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'PETR4');
    }

    public function test_can_search_assets_by_company_name(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create([
            'company_category_id' => $category->id,
            'name' => 'Petrobras S.A.',
        ]);

        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=Petrobras', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_can_filter_search_by_category(): void
    {
        $acoes = CompanyCategory::factory()->acoes()->create();
        $fiis = CompanyCategory::factory()->fii()->create();

        $companyAcao = Company::factory()->create(['company_category_id' => $acoes->id]);
        $companyFii = Company::factory()->create(['company_category_id' => $fiis->id]);

        CompanyTicker::factory()->create([
            'company_id' => $companyAcao->id,
            'code' => 'TEST3',
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $companyFii->id,
            'code' => 'TEST11',
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson(
            "/api/companies?search=TEST&category_id={$acoes->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'TEST3');
    }

    public function test_search_excludes_inactive_tickers(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'ACTIVE4',
            'status' => true,
        ]);
        CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'INACTIVE3',
            'status' => false,
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=ACTIVE', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/companies?search=INACTIVE', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_search_requires_minimum_characters(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=P', $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_search_respects_limit_parameter(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        CompanyTicker::factory()->count(10)->create([
            'company_id' => $company->id,
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies?search=test&limit=3', $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertLessThanOrEqual(3, count($response->json('data')));
    }

    public function test_cannot_search_without_authentication(): void
    {
        $response = $this->getJson('/api/companies?search=PETR');

        $response->assertStatus(401);
    }
}
