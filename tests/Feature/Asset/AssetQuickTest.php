<?php

namespace Tests\Feature\Asset;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetQuickTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_quick_companies_from_open_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->for($auth['user'])->create();

        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);

        $olderTicker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'OLD4',
        ]);
        $newerTicker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'NEW4',
        ]);
        $closedTicker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'CLOSE4',
        ]);
        $zeroQtyTicker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'ZERO4',
        ]);

        Consolidated::factory()
            ->forAccount($account)
            ->forTicker($olderTicker)
            ->state(['updated_at' => now()->subDays(2)])
            ->create();

        Consolidated::factory()
            ->forAccount($account)
            ->forTicker($newerTicker)
            ->state(['updated_at' => now()->subDay()])
            ->create();

        Consolidated::factory()
            ->forAccount($account)
            ->forTicker($closedTicker)
            ->closed()
            ->create();

        Consolidated::factory()
            ->forAccount($account)
            ->forTicker($zeroQtyTicker)
            ->state([
                'closed' => false,
                'quantity_current' => 0,
            ])
            ->create();

        $otherUser = Account::factory()->create();
        $otherTicker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'OTHER4',
        ]);

        Consolidated::factory()
            ->forAccount($otherUser)
            ->forTicker($otherTicker)
            ->create();

        $response = $this->getJson('/api/companies/quick?limit=2', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.code', 'NEW4')
            ->assertJsonPath('data.1.code', 'OLD4');

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertNotContains('CLOSE4', $codes);
        $this->assertNotContains('ZERO4', $codes);
        $this->assertNotContains('OTHER4', $codes);
    }

    public function test_quick_companies_requires_authentication(): void
    {
        $response = $this->getJson('/api/companies/quick');

        $response->assertStatus(401);
    }
}
