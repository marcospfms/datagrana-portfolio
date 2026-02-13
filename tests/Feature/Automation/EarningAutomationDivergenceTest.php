<?php

namespace Tests\Feature\Automation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningAutomationDivergenceTest extends TestCase
{
    use RefreshDatabase;
    use AutomationTestHelpers;

    public function test_can_fix_divergence_with_update(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $targetEarning = $scenario['divergenceEarnings'][0];

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningDivergence']->id}/divergence",
            [
                'earning_id' => $targetEarning->id,
                'manter_valores_originais' => false,
            ],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $scenario['companyEarningDivergence']->load('earningType');
        $calculated = $scenario['companyEarningDivergence']->calculateValues(10);

        $this->assertDatabaseHas('earnings', [
            'id' => $targetEarning->id,
            'company_earning_id' => $scenario['companyEarningDivergence']->id,
            'net_value' => $calculated['net_value'],
        ]);
    }

    public function test_can_fix_divergence_keep_original(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $targetEarning = $scenario['divergenceEarnings'][0];

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningDivergence']->id}/divergence",
            [
                'earning_id' => $targetEarning->id,
                'manter_valores_originais' => true,
            ],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('earnings', [
            'id' => $targetEarning->id,
            'company_earning_id' => $scenario['companyEarningDivergence']->id,
            'net_value' => $targetEarning->net_value,
        ]);
    }

    public function test_requires_earning_id(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningDivergence']->id}/divergence",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['earning_id']);
    }

    public function test_cannot_fix_divergence_without_paid_or_trial(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $targetEarning = $scenario['divergenceEarnings'][0];

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningDivergence']->id}/divergence",
            [
                'earning_id' => $targetEarning->id,
                'manter_valores_originais' => false,
            ],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_LIMIT_EXCEEDED');
    }
}
