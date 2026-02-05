<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserSubscriptionUsage;
use Tests\TestCase;

class UserSubscriptionTest extends TestCase
{
    public function test_can_get_current_subscription(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_name' => 'Starter',
            'plan_slug' => 'starter',
            'status' => 'active',
            'is_paid' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        // Create usage for the subscription
        UserSubscriptionUsage::create([
            'user_id' => $user->id,
            'user_subscription_id' => $subscription->id,
            'current_portfolios' => 0,
            'current_compositions' => 0,
            'current_positions' => 0,
            'current_accounts' => 0,
        ]);

        $response = $this->getJson('/api/subscription/current', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'plan' => [
                        'name',
                        'slug',
                        'price_monthly',
                    ],
                    'status',
                    'is_active',
                    'has_had_paid_plan',
                    'starts_at',
                    'ends_at',
                    'renews_at',
                    'usage' => [
                        'current_portfolios',
                        'current_compositions',
                        'current_positions',
                        'current_accounts',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'plan' => [
                        'slug' => 'starter',
                    ],
                    'status' => 'active',
                    'has_had_paid_plan' => true,
                ],
                'message' => 'Assinatura atual carregada com sucesso.',
            ]);
    }

    public function test_current_subscription_creates_free_if_none_exists(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $response = $this->getJson('/api/subscription/current', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $this->assertDatabaseHas('user_subscriptions', [
            'user_id' => $user->id,
            'plan_slug' => 'free',
            'status' => 'active',
        ]);
    }

    public function test_has_had_paid_plan_is_true_when_user_had_paid_subscription(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'canceled',
            'is_paid' => true,
            'ends_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/subscription/current', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'has_had_paid_plan' => true,
                ],
            ]);
    }

    public function test_has_had_paid_plan_is_false_when_user_never_had_paid_subscription(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $plan = SubscriptionPlan::where('slug', 'free')->firstOrFail();
        $subscription = UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'free',
            'status' => 'active',
            'is_paid' => false,
        ]);

        // Create usage for the subscription
        UserSubscriptionUsage::create([
            'user_id' => $user->id,
            'user_subscription_id' => $subscription->id,
            'current_portfolios' => 0,
            'current_compositions' => 0,
            'current_positions' => 0,
            'current_accounts' => 0,
        ]);

        $response = $this->getJson('/api/subscription/current', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'has_had_paid_plan' => false,
                ],
            ]);
    }

    public function test_can_get_subscription_history(): void
    {
        $auth = $this->createAuthenticatedUser();
        $user = $auth['user'];

        // Clean up any existing subscription data for the user
        UserSubscriptionUsage::where('user_id', $user->id)->delete();
        UserSubscription::where('user_id', $user->id)->delete();

        $starter = SubscriptionPlan::where('slug', 'starter')->firstOrFail();
        $pro = SubscriptionPlan::where('slug', 'pro')->firstOrFail();

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $starter->id,
            'plan_name' => 'Starter',
            'plan_slug' => 'starter',
            'status' => 'canceled',
            'created_at' => now()->subMonths(2),
        ]);

        UserSubscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => $pro->id,
            'plan_name' => 'Pro',
            'plan_slug' => 'pro',
            'status' => 'active',
            'created_at' => now()->subMonth(),
        ]);

        $response = $this->getJson('/api/subscription/history', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'plan' => [
                            'name',
                            'slug',
                        ],
                        'status',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Historico de assinaturas carregado com sucesso.',
            ]);

        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('pro', $data[0]['plan']['slug']);
        $this->assertEquals('starter', $data[1]['plan']['slug']);
    }

    public function test_cannot_get_current_subscription_without_authentication(): void
    {
        $response = $this->getJson('/api/subscription/current');

        $response->assertStatus(401);
    }

    public function test_cannot_get_history_without_authentication(): void
    {
        $response = $this->getJson('/api/subscription/history');

        $response->assertStatus(401);
    }

    public function test_returns_only_own_subscriptions_in_history(): void
    {
        $auth1 = $this->createAuthenticatedUser();
        $user1 = $auth1['user'];

        $auth2 = $this->createAuthenticatedUser();
        $user2 = $auth2['user'];

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        UserSubscription::factory()->create([
            'user_id' => $user1->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
        ]);

        UserSubscription::factory()->create([
            'user_id' => $user2->id,
            'subscription_plan_id' => $plan->id,
            'plan_slug' => 'starter',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/subscription/history', $this->authHeaders($auth1['token']));

        $response->assertStatus(200);

        $data = $response->json('data');
        foreach ($data as $sub) {
            $this->assertEquals($user1->id, UserSubscription::find($sub['id'])->user_id);
        }
    }
}
