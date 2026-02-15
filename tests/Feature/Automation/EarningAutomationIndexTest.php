<?php

namespace Tests\Feature\Automation;

use App\Models\CompanyEarning;
use App\Models\CompanyTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningAutomationIndexTest extends TestCase
{
    use RefreshDatabase;
    use AutomationTestHelpers;

    public function test_can_list_automations_for_user(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->getJson('/api/automations/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.count_consolidate', 1)
            ->assertJsonPath('data.summary.count_not_registered', 1)
            ->assertJsonPath('data.summary.count_divergences', 1);

        $ids = collect($response->json('data.data'))
            ->flatMap(fn ($group) => collect($group['data'] ?? [])->pluck('id'))
            ->all();
        $this->assertContains($scenario['companyEarningConsolidate']->id, $ids);
        $this->assertContains($scenario['companyEarningDivergence']->id, $ids);
        $this->assertContains($scenario['companyEarningNotRegistered']->id, $ids);
    }

    public function test_omits_items_without_quantity_until_approved(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $zeroQuantity = $this->createZeroQuantityCompanyEarning($auth['user'], now()->subMonths(6));

        $response = $this->getJson('/api/automations/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))
            ->flatMap(fn ($group) => collect($group['data'] ?? [])->pluck('id'))
            ->all();
        $this->assertNotContains($zeroQuantity->id, $ids);
        $this->assertContains($scenario['companyEarningConsolidate']->id, $ids);
    }

    public function test_cannot_list_without_authentication(): void
    {
        $response = $this->getJson('/api/automations/earnings');

        $response->assertStatus(401);
    }

    public function test_includes_closed_positions_when_holding_existed_on_approved_date(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        CompanyTransaction::factory()->create([
            'consolidated_id' => $scenario['consolidated']->id,
            'date' => now()->subHours(1),
            'operation' => 'V',
            'quantity' => 5,
            'price' => 10,
            'total_value' => 50,
        ]);

        $scenario['consolidated']->update([
            'quantity_current' => 0,
            'closed' => true,
        ]);

        $response = $this->getJson('/api/automations/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))
            ->flatMap(fn ($group) => collect($group['data'] ?? [])->pluck('id'))
            ->all();

        $this->assertContains($scenario['companyEarningConsolidate']->id, $ids);
    }

    public function test_excludes_old_closed_positions_outside_recent_window(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $oldEligibleEarning = CompanyEarning::factory()->create([
            'company_ticker_id' => $scenario['ticker']->id,
            'earning_type_id' => $scenario['earningType']->id,
            'approved_date' => now()->subMonths(4),
            'payment_date' => now()->subMonths(4)->addDays(2),
            'value' => 1.2,
            'status' => true,
        ]);

        CompanyTransaction::factory()->create([
            'consolidated_id' => $scenario['consolidated']->id,
            'date' => now()->subMonths(3),
            'operation' => 'V',
            'quantity' => 5,
            'price' => 10,
            'total_value' => 50,
        ]);

        $scenario['consolidated']->update([
            'quantity_current' => 0,
            'closed' => true,
        ]);

        $response = $this->getJson('/api/automations/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))
            ->flatMap(fn ($group) => collect($group['data'] ?? [])->pluck('id'))
            ->all();

        $this->assertNotContains($oldEligibleEarning->id, $ids);
    }

    public function test_excludes_open_positions_with_payment_older_than_12_months(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $oldOpenEarning = CompanyEarning::factory()->create([
            'company_ticker_id' => $scenario['ticker']->id,
            'earning_type_id' => $scenario['earningType']->id,
            'approved_date' => now()->subMonths(13)->subDays(2),
            'payment_date' => now()->subMonths(13),
            'value' => 1.1,
            'status' => true,
        ]);

        $response = $this->getJson('/api/automations/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $ids = collect($response->json('data.data'))
            ->flatMap(fn ($group) => collect($group['data'] ?? [])->pluck('id'))
            ->all();

        $this->assertNotContains($oldOpenEarning->id, $ids);
        $this->assertContains($scenario['companyEarningConsolidate']->id, $ids);
    }
}
