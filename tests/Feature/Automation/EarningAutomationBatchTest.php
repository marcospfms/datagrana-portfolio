<?php

namespace Tests\Feature\Automation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningAutomationBatchTest extends TestCase
{
    use RefreshDatabase;
    use AutomationTestHelpers;

    public function test_can_consolidate_batch(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            '/api/automations/earnings/consolidate-batch',
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('earnings', [
            'id' => $scenario['exactEarning']->id,
            'company_earning_id' => $scenario['companyEarningConsolidate']->id,
        ]);

        $this->assertDatabaseHas('earnings', [
            'id' => $scenario['divergenceEarnings'][0]->id,
            'company_earning_id' => null,
        ]);
    }

    public function test_can_register_batch(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            '/api/automations/earnings/register-batch',
            ['account_id' => $scenario['account']->id],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('earnings', [
            'company_earning_id' => $scenario['companyEarningNotRegistered']->id,
            'consolidated_id' => $scenario['consolidated']->id,
        ]);

        $this->assertDatabaseMissing('earnings', [
            'company_earning_id' => $scenario['companyEarningDivergence']->id,
        ]);
    }

    public function test_register_batch_requires_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);

        $response = $this->postJson(
            '/api/automations/earnings/register-batch',
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_batch_actions_require_paid_or_trial(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson(
            '/api/automations/earnings/consolidate-batch',
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_LIMIT_EXCEEDED');
    }
}
