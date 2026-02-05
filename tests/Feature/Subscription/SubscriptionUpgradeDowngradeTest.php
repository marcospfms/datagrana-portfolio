<?php

namespace Tests\Feature\Subscription;

use App\Models\Portfolio;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\SubscriptionLimitService;
use Carbon\Carbon;
use Tests\TestCase;

class SubscriptionUpgradeDowngradeTest extends TestCase
{
    protected SubscriptionLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionLimitService::class);
    }

    public function test_upgrade_updates_limits_immediately(): void
    {
        $user = User::factory()->create();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        $starterPlan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $freePlan->id,
            'plan_slug' => 'free',
            'status' => 'canceled',
            'ends_at' => now()->subDay(),
        ]);

        $subscription = $this->service->createSubscriptionFromPlan($user, $starterPlan);

        $this->assertEquals('starter', $subscription->plan_slug);
        $this->assertTrue($subscription->is_paid);
        $this->assertEquals($starterPlan->getLimitsArray(), $subscription->limits_snapshot);
        $this->assertEquals($starterPlan->getFeaturesArray(), $subscription->features_snapshot);

        $this->assertTrue($this->service->canCreatePortfolio($user));
        $this->assertTrue($this->service->hasFullCrossingAccess($user));
    }

    public function test_downgrade_schedules_pending_plan(): void
    {
        $user = User::factory()->create();

        $starterPlan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();

        $currentSubscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $starterPlan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'ends_at' => now()->addMonth(),
        ]);

        $pendingDate = now()->addMonth();
        $currentSubscription->update([
            'pending_plan_slug' => $freePlan->slug,
            'pending_effective_at' => $pendingDate,
        ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'id' => $currentSubscription->id,
            'pending_plan_slug' => 'free',
        ]);

        $subscription = UserSubscription::find($currentSubscription->id);
        $this->assertEquals($pendingDate->toDateString(), $subscription->pending_effective_at->toDateString());
    }

    public function test_product_change_downgrade_schedules_pending_plan(): void
    {
        $user = User::factory()->create();

        $proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();
        $starterPlan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        $currentSubscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $proPlan->id,
            'plan_slug' => 'pro',
            'status' => 'active',
            'is_paid' => true,
            'ends_at' => now()->addMonth(),
        ]);

        $webhookPayload = [
            'event' => [
                'id' => 'evt_product_change_123',
                'type' => 'PRODUCT_CHANGE',
                'app_user_id' => (string) $user->id,
                'subscriber_id' => 'sub_123',
                'product_id' => $starterPlan->revenuecat_product_id,
                'entitlement_identifiers' => ['starter_plan'],
                'store' => 'PLAY_STORE',
                'original_transaction_id' => 'txn_123',
                'expiration_at_ms' => now()->addMonth()->getTimestamp() * 1000,
                'event_timestamp_ms' => now()->getTimestamp() * 1000,
                'purchased_at_ms' => now()->addMonth()->getTimestamp() * 1000,
            ],
        ];

        config(['services.revenuecat.webhook_auth_header' => 'test-auth-header']);

        $response = $this->postJson('/api/webhooks/revenuecat', $webhookPayload, [
            'Authorization' => 'test-auth-header',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_subscriptions', [
            'id' => $currentSubscription->id,
            'pending_plan_slug' => 'starter',
        ]);
    }

    public function test_limits_apply_based_on_active_subscription(): void
    {
        $user = User::factory()->create();

        $freePlan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        $starterPlan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $starterPlan->id,
            'plan_slug' => 'starter',
            'status' => 'canceled',
            'is_paid' => true,
            'ends_at' => now()->subDay(),
        ]);

        $this->service->createFreeSubscription($user);

        $freeLimit = $freePlan->getLimit('max_portfolios');
        $starterLimit = $starterPlan->getLimit('max_portfolios');

        $this->assertNotEquals($freeLimit, $starterLimit);
        $this->assertTrue($this->service->canCreatePortfolio($user));
    }

    public function test_upgrade_cancels_previous_active_subscription(): void
    {
        $user = User::factory()->create();

        $starterPlan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $proPlan = SubscriptionPlan::where('slug', 'pro')->firstOrFail();

        $oldSubscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $starterPlan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'ends_at' => now()->addMonth(),
        ]);

        $webhookPayload = [
            'event' => [
                'id' => 'evt_upgrade_123',
                'type' => 'INITIAL_PURCHASE',
                'app_user_id' => (string) $user->id,
                'subscriber_id' => 'sub_123',
                'product_id' => $proPlan->revenuecat_product_id,
                'entitlement_identifiers' => ['pro_plan'],
                'store' => 'PLAY_STORE',
                'original_transaction_id' => 'txn_pro_123',
                'expiration_at_ms' => now()->addMonth()->getTimestamp() * 1000,
                'event_timestamp_ms' => now()->getTimestamp() * 1000,
                'purchased_at_ms' => now()->getTimestamp() * 1000,
            ],
        ];

        config(['services.revenuecat.webhook_auth_header' => 'test-auth-header']);

        $response = $this->postJson('/api/webhooks/revenuecat', $webhookPayload, [
            'Authorization' => 'test-auth-header',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_subscriptions', [
            'id' => $oldSubscription->id,
            'status' => 'canceled',
        ]);
    }
}
