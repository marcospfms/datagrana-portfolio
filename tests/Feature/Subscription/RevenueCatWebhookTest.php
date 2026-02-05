<?php

namespace Tests\Feature\Subscription;

use App\Models\RevenueCatWebhookLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevenueCatWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.revenuecat.webhook_auth_header' => 'test-auth-header']);
    }

    private function webhookPayload(string $eventType, array $overrides = []): array
    {
        $base = [
            'event' => [
                'id' => 'evt_' . uniqid(),
                'type' => $eventType,
                'app_user_id' => (string) ($overrides['user_id'] ?? 1),
                'subscriber_id' => 'sub_' . uniqid(),
                'product_id' => $overrides['product_id'] ?? 'starter_monthly',
                'entitlement_identifiers' => ['starter_plan'],
                'store' => 'PLAY_STORE',
                'original_transaction_id' => $overrides['original_transaction_id'] ?? 'txn_' . uniqid(),
                'expiration_at_ms' => $overrides['expiration_at_ms'] ?? (now()->addMonth()->getTimestamp() * 1000),
                'event_timestamp_ms' => $overrides['event_timestamp_ms'] ?? (now()->getTimestamp() * 1000),
                'purchased_at_ms' => $overrides['purchased_at_ms'] ?? (now()->getTimestamp() * 1000),
                'period_type' => $overrides['period_type'] ?? 'NORMAL',
            ],
        ];

        if (isset($overrides['event'])) {
            $base['event'] = array_merge($base['event'], $overrides['event']);
        }

        return $base;
    }

    private function webhookHeaders(): array
    {
        return [
            'Authorization' => 'test-auth-header',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    public function test_initial_purchase_creates_paid_subscription(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        $payload = $this->webhookPayload('INITIAL_PURCHASE', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['status' => 'success'],
            ]);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'renewal_count' => 0,
        ]);
    }

    public function test_renewal_updates_renews_at_and_increments_renewal_count(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $originalTransactionId = 'txn_original_123';

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'renewal_count' => 1,
            'revenuecat_original_transaction_id' => $originalTransactionId,
        ]);

        $newExpiration = now()->addMonths(2);
        $payload = $this->webhookPayload('RENEWAL', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
            'original_transaction_id' => $originalTransactionId,
            'expiration_at_ms' => $newExpiration->getTimestamp() * 1000,
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_slug' => 'starter',
            'renewal_count' => 2,
        ]);

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();
        $this->assertEquals($newExpiration->toDateString(), $subscription->renews_at->toDateString());
    }

    public function test_cancellation_in_trial_cuts_access_immediately(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $originalTransactionId = 'txn_trial_123';

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'revenuecat_original_transaction_id' => $originalTransactionId,
        ]);

        $payload = $this->webhookPayload('CANCELLATION', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
            'original_transaction_id' => $originalTransactionId,
            'period_type' => 'TRIAL',
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'status' => 'canceled',
            'plan_slug' => 'starter',
        ]);

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();
        $this->assertNotNull($subscription->canceled_at);
        $this->assertNotNull($subscription->ends_at);
    }

    public function test_cancellation_outside_trial_maintains_access_until_ends_at(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $originalTransactionId = 'txn_normal_123';
        $endsAt = now()->addMonth();

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'ends_at' => $endsAt,
            'revenuecat_original_transaction_id' => $originalTransactionId,
        ]);

        $payload = $this->webhookPayload('CANCELLATION', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
            'original_transaction_id' => $originalTransactionId,
            'period_type' => 'NORMAL',
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200);

        $subscription = UserSubscription::where('user_id', $user->id)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();
        $this->assertNotNull($subscription->canceled_at);
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($endsAt->toDateString(), $subscription->ends_at->toDateString());
    }

    public function test_expiration_marks_subscription_as_expired(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $originalTransactionId = 'txn_expire_123';

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'revenuecat_original_transaction_id' => $originalTransactionId,
        ]);

        $payload = $this->webhookPayload('EXPIRATION', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
            'original_transaction_id' => $originalTransactionId,
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'status' => 'expired',
            'plan_slug' => 'starter',
        ]);
    }

    public function test_invalid_auth_header_returns_error(): void
    {
        $payload = $this->webhookPayload('INITIAL_PURCHASE');

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, [
            'Authorization' => 'invalid-header',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false,
                'message' => 'Erro ao processar webhook.',
            ]);
    }

    public function test_duplicate_event_is_ignored(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $eventId = 'evt_duplicate_123';

        RevenueCatWebhookLog::create([
            'event_id' => $eventId,
            'event_type' => 'INITIAL_PURCHASE',
            'app_user_id' => (string) $user->id,
            'status' => 'processed',
            'processed_at' => now(),
            'payload' => [],
        ]);

        $payload = $this->webhookPayload('INITIAL_PURCHASE', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
            'event' => ['id' => $eventId],
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseCount('user_subscriptions', 1);
        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_slug' => 'free',
        ]);
    }

    public function test_webhook_logs_are_created(): void
    {
        $user = User::factory()->create();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $eventId = 'evt_log_test_123';

        $payload = $this->webhookPayload('INITIAL_PURCHASE', [
            'user_id' => $user->id,
            'product_id' => $plan->revenuecat_product_id,
            'event' => ['id' => $eventId],
        ]);

        $response = $this->postJson('/api/webhooks/revenuecat', $payload, $this->webhookHeaders());

        $response->assertStatus(200);

        $this->assertDatabaseHas('revenuecat_webhook_logs', [
            'event_id' => $eventId,
            'event_type' => 'INITIAL_PURCHASE',
            'status' => 'processed',
        ]);
    }
}
