<?php

namespace Tests\Feature\Asset;

use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_asset_details(): void
    {
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create([
            'company_category_id' => $category->id,
            'name' => 'Petrobras',
        ]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'PETR4',
            'last_price' => 35.50,
        ]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson("/api/companies/{$ticker->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'code',
                    'last_price',
                    'company' => [
                        'id',
                        'name',
                        'category',
                    ],
                ],
            ])
            ->assertJsonPath('data.code', 'PETR4')
            ->assertJsonPath('data.company.name', 'Petrobras');
    }

    public function test_returns_404_for_nonexistent_asset(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/companies/99999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }

    public function test_cannot_view_asset_without_authentication(): void
    {
        $ticker = CompanyTicker::factory()->create();

        $response = $this->getJson("/api/companies/{$ticker->id}");

        $response->assertStatus(401);
    }
}
