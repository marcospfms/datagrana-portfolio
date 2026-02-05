<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use Tests\TestCase;

class SubscriptionPlanTest extends TestCase
{
    public function test_can_list_active_subscription_plans(): void
    {
        $auth = $this->createAuthenticatedUser();

        SubscriptionPlan::factory()->create([
            'name' => 'Plano Inativo',
            'slug' => 'inactive',
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/subscription-plans', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'slug',
                        'description',
                        'price_monthly',
                        'configs',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Planos de assinatura carregados com sucesso.',
            ]);

        $data = $response->json('data');
        foreach ($data as $plan) {
            $this->assertNotEquals('inactive', $plan['slug']);
        }
    }

    public function test_subscription_plans_are_ordered_by_display_order(): void
    {
        $auth = $this->createAuthenticatedUser();

        // Delete subscriptions first to avoid FK constraint violation
        \App\Models\UserSubscription::query()->delete();
        SubscriptionPlan::query()->delete();

        $plan2 = SubscriptionPlan::factory()->create([
            'name' => 'Plano B',
            'slug' => 'plan-b',
            'display_order' => 2,
            'is_active' => true,
        ]);

        $plan1 = SubscriptionPlan::factory()->create([
            'name' => 'Plano A',
            'slug' => 'plan-a',
            'display_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/subscription-plans', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('plan-a', $data[0]['slug']);
        $this->assertEquals('plan-b', $data[1]['slug']);
    }

    public function test_plans_include_configs(): void
    {
        $auth = $this->createAuthenticatedUser();

        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        $response = $this->getJson('/api/subscription-plans', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $data = $response->json('data');
        $starterPlan = collect($data)->firstWhere('slug', 'starter');

        $this->assertNotNull($starterPlan);
        $this->assertArrayHasKey('configs', $starterPlan);
        $this->assertIsArray($starterPlan['configs']);
    }

    public function test_can_show_subscription_plan(): void
    {
        $auth = $this->createAuthenticatedUser();
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        $response = $this->getJson("/api/subscription-plans/{$plan->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'description',
                    'price_monthly',
                    'configs',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $plan->id,
                    'slug' => 'starter',
                ],
                'message' => 'Plano carregado com sucesso.',
            ]);
    }

    public function test_returns_404_for_nonexistent_plan(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/subscription-plans/99999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }

    public function test_cannot_list_plans_without_authentication(): void
    {
        $response = $this->getJson('/api/subscription-plans');

        $response->assertStatus(401);
    }

    public function test_cannot_show_plan_without_authentication(): void
    {
        $plan = SubscriptionPlan::where('slug', 'starter')->firstOrFail();

        $response = $this->getJson("/api/subscription-plans/{$plan->id}");

        $response->assertStatus(401);
    }
}
