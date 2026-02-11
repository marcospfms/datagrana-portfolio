<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Models\EarningType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_show_own_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $category = CompanyCategory::factory()->create();
        $company = Company::factory()->create(['company_category_id' => $category->id]);
        $ticker = CompanyTicker::factory()->create([
            'company_id' => $company->id,
            'code' => 'TEST3',
        ]);
        $type = EarningType::factory()->create();
        $consolidated = Consolidated::factory()->forAccount($account)->forTicker($ticker)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create([
            'earning_type_id' => $type->id,
            'net_value' => 100.50,
            'gross_value' => 120.00,
            'tax' => 19.50,
        ]);

        $response = $this->getJson(
            "/api/earnings/{$earning->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $earning->id)
            ->assertJsonPath('data.net_value', '100.50000000')
            ->assertJsonPath('data.gross_value', '120.00000000')
            ->assertJsonPath('data.tax', '19.50000000')
            ->assertJsonPath('data.earning_type.id', $type->id)
            ->assertJsonPath('data.consolidated.id', $consolidated->id)
            ->assertJsonPath('data.consolidated.company_ticker.code', 'TEST3');
    }

    public function test_cannot_show_other_user_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAuth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create(['user_id' => $otherAuth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($otherAccount)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create();

        $response = $this->getJson(
            "/api/earnings/{$earning->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Provento nao encontrado.',
            ]);
    }

    public function test_cannot_show_earning_without_authentication(): void
    {
        $account = Account::factory()->create();
        $consolidated = Consolidated::factory()->forAccount($account)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create();

        $response = $this->getJson("/api/earnings/{$earning->id}");

        $response->assertStatus(401);
    }

    public function test_returns_404_for_nonexistent_earning(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson(
            '/api/earnings/99999',
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Provento nao encontrado.',
            ]);
    }
}
