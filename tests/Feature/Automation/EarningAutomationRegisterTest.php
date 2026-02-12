<?php

namespace Tests\Feature\Automation;

use App\Models\Account;
use App\Models\CompanyEarning;
use App\Models\EarningType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningAutomationRegisterTest extends TestCase
{
    use RefreshDatabase;
    use AutomationTestHelpers;

    public function test_can_register_when_not_found(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningNotRegistered']->id}/register",
            ['account_id' => $scenario['account']->id],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $this->assertDatabaseHas('earnings', [
            'company_earning_id' => $scenario['companyEarningNotRegistered']->id,
            'consolidated_id' => $scenario['consolidated']->id,
        ]);
    }

    public function test_register_applies_tax_rules(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $earningType = EarningType::factory()->create(['name' => 'JCP', 'label' => 'JCP']);
        $companyEarning = CompanyEarning::factory()->create([
            'company_ticker_id' => $scenario['ticker']->id,
            'earning_type_id' => $earningType->id,
            'approved_date' => now()->subDays(9),
            'payment_date' => now()->subDays(7),
            'value' => 1,
        ]);

        $response = $this->postJson(
            "/api/automations/earnings/{$companyEarning->id}/register",
            ['account_id' => $scenario['account']->id],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);

        $earning = \App\Models\Earning::where('company_earning_id', $companyEarning->id)->firstOrFail();

        $this->assertEqualsWithDelta(10.0, (float) $earning->gross_value, 0.001);
        $this->assertEqualsWithDelta(8.5, (float) $earning->net_value, 0.001);
        $this->assertEqualsWithDelta(1.5, (float) $earning->tax, 0.001);
    }

    public function test_requires_account_id(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningNotRegistered']->id}/register",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_id']);
    }

    public function test_cannot_register_with_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createPaidSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $otherAccount = Account::factory()->create();

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningNotRegistered']->id}/register",
            ['account_id' => $otherAccount->id],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }

    public function test_cannot_register_without_paid_or_trial(): void
    {
        $auth = $this->createAuthenticatedUser();
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningNotRegistered']->id}/register",
            ['account_id' => $scenario['account']->id],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403)
            ->assertJsonPath('error_code', 'SUBSCRIPTION_LIMIT_EXCEEDED');
    }

    public function test_trial_subscription_allows_register(): void
    {
        $auth = $this->createAuthenticatedUser();
        $this->createTrialSubscription($auth['user']);
        $scenario = $this->buildAutomationScenario($auth['user']);

        $response = $this->postJson(
            "/api/automations/earnings/{$scenario['companyEarningNotRegistered']->id}/register",
            ['account_id' => $scenario['account']->id],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);
    }
}
