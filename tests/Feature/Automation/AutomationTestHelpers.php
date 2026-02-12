<?php

namespace Tests\Feature\Automation;

use App\Models\Account;
use App\Models\CompanyEarning;
use App\Models\CompanyTicker;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Models\EarningType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Carbon;

trait AutomationTestHelpers
{
    protected function createPaidSubscription(User $user, string $slug = 'starter'): UserSubscription
    {
        $plan = SubscriptionPlan::where('slug', $slug)->firstOrFail();

        return app(SubscriptionLimitService::class)->createSubscriptionFromPlan($user, $plan);
    }

    protected function createTrialSubscription(User $user, string $slug = 'starter'): UserSubscription
    {
        $plan = SubscriptionPlan::where('slug', $slug)->firstOrFail();

        return UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'limits_snapshot' => $plan->getLimitsArray(),
            'features_snapshot' => $plan->getFeaturesArray(),
            'status' => 'active',
            'starts_at' => now(),
            'trial_ends_at' => now()->addDays(7),
            'is_paid' => false,
        ]);
    }

    protected function buildAutomationScenario(User $user): array
    {
        $account = Account::factory()->create(['user_id' => $user->id]);
        $ticker = CompanyTicker::factory()->create();
        $earningType = EarningType::factory()->create(['name' => 'Dividendo', 'label' => 'Dividendo']);

        $createdAt = now()->subMonths(6);
        $consolidated = Consolidated::factory()
            ->forAccount($account)
            ->forTicker($ticker)
            ->create([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'quantity_current' => 10,
            ]);

        CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'date' => now()->subMonths(5),
            'operation' => 'C',
            'quantity' => 10,
            'price' => 10,
            'total_value' => 100,
        ]);

        CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'date' => now()->subDays(1),
            'operation' => 'V',
            'quantity' => 5,
            'price' => 10,
            'total_value' => 50,
        ]);

        $baseApproved = now()->subDays(12);
        $basePayment = now()->subDays(10);

        $companyEarningConsolidate = CompanyEarning::factory()->create([
            'company_ticker_id' => $ticker->id,
            'earning_type_id' => $earningType->id,
            'approved_date' => $baseApproved,
            'payment_date' => $basePayment,
            'value' => 1.5,
        ]);

        $companyEarningConsolidate->load('earningType');
        $calculated = $companyEarningConsolidate->calculateValues(10);

        $exactEarning = Earning::factory()->create([
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $earningType->id,
            'date' => $basePayment->toDateString(),
            'quantity' => 10,
            'net_value' => $calculated['net_value'],
            'gross_value' => $calculated['gross_value'],
            'tax' => $calculated['tax'],
            'company_earning_id' => null,
        ]);

        $companyEarningDivergence = CompanyEarning::factory()->create([
            'company_ticker_id' => $ticker->id,
            'earning_type_id' => $earningType->id,
            'approved_date' => $baseApproved->copy()->subDays(2),
            'payment_date' => $basePayment->copy()->subDays(2),
            'value' => 1.2,
        ]);

        $divergenceEarningA = Earning::factory()->create([
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $earningType->id,
            'date' => $companyEarningDivergence->payment_date->toDateString(),
            'quantity' => 10,
            'net_value' => 5,
            'gross_value' => 5,
            'tax' => 0,
            'company_earning_id' => null,
        ]);

        $divergenceEarningB = Earning::factory()->create([
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $earningType->id,
            'date' => $companyEarningDivergence->payment_date->toDateString(),
            'quantity' => 8,
            'net_value' => 4,
            'gross_value' => 4,
            'tax' => 0,
            'company_earning_id' => null,
        ]);

        $companyEarningNotRegistered = CompanyEarning::factory()->create([
            'company_ticker_id' => $ticker->id,
            'earning_type_id' => $earningType->id,
            'approved_date' => $baseApproved->copy()->subDays(4),
            'payment_date' => $basePayment->copy()->subDays(4),
            'value' => 0.8,
        ]);

        return [
            'account' => $account,
            'ticker' => $ticker,
            'earningType' => $earningType,
            'consolidated' => $consolidated,
            'companyEarningConsolidate' => $companyEarningConsolidate,
            'companyEarningDivergence' => $companyEarningDivergence,
            'companyEarningNotRegistered' => $companyEarningNotRegistered,
            'exactEarning' => $exactEarning,
            'divergenceEarnings' => [$divergenceEarningA, $divergenceEarningB],
        ];
    }

    protected function createZeroQuantityCompanyEarning(User $user, Carbon $firstPurchaseDate): CompanyEarning
    {
        $account = Account::factory()->create(['user_id' => $user->id]);
        $ticker = CompanyTicker::factory()->create();

        $consolidated = Consolidated::factory()
            ->forAccount($account)
            ->forTicker($ticker)
            ->create([
                'created_at' => $firstPurchaseDate,
                'updated_at' => $firstPurchaseDate,
                'quantity_current' => 10,
            ]);

        CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'date' => now()->subDays(8),
            'operation' => 'C',
            'quantity' => 10,
            'price' => 10,
            'total_value' => 100,
        ]);

        CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'date' => now()->subDays(7),
            'operation' => 'V',
            'quantity' => 10,
            'price' => 10,
            'total_value' => 100,
        ]);

        $earningType = EarningType::factory()->create(['name' => 'Dividendo', 'label' => 'Dividendo']);

        return CompanyEarning::factory()->create([
            'company_ticker_id' => $ticker->id,
            'earning_type_id' => $earningType->id,
            'approved_date' => now()->subDays(6),
            'payment_date' => now()->subDays(5),
            'value' => 1,
        ]);
    }
}
