<?php

namespace Tests\Feature\Automation;

use App\Models\CompanyEarning;
use App\Models\Earning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningAutomationConsolidateTest extends TestCase
{
    use RefreshDatabase;
    use AutomationTestHelpers;

    public function test_can_consolidate_exact_match(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningConsolidate']->id}/consolidate",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('earnings', [
            'id' => $scenario['exactEarning']->id,
            'company_earning_id' => $scenario['companyEarningConsolidate']->id,
        ]);
    }

    public function test_cannot_consolidate_when_no_match(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);
        $companyEarning = CompanyEarning::factory()->create([
            'company_ticker_id' => $scenario['ticker']->id,
            'earning_type_id' => $scenario['earningType']->id,
            'approved_date' => now()->subDays(9),
            'payment_date' => now()->subDays(7),
            'value' => 2,
        ]);

        $response = $this->postJson(
            "/api/automations/earnings/{$companyEarning->id}/consolidate",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422);
    }

    public function test_cannot_consolidate_without_paid_or_trial(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningConsolidate']->id}/consolidate",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_LIMIT_EXCEEDED');
    }

    public function test_cannot_consolidate_other_user_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);

        $other = $this->createAuthenticatedUser();
        $otherScenario = $this->buildAutomationScenario($other['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$otherScenario['companyEarningConsolidate']->id}/consolidate",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(404);

        $this->assertDatabaseHas('earnings', [
            'id' => $otherScenario['exactEarning']->id,
            'company_earning_id' => null,
        ]);
    }
}
