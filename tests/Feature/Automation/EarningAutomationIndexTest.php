<?php

namespace Tests\Feature\Automation;

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

        $ids = collect($response->json('data.data'))->pluck('id')->all();
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

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertNotContains($zeroQuantity->id, $ids);
        $this->assertContains($scenario['companyEarningConsolidate']->id, $ids);
    }

    public function test_cannot_list_without_authentication(): void
    {
        $response = $this->getJson('/api/automations/earnings');

        $response->assertStatus(401);
    }
}
