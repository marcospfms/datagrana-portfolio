<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\SubscriptionLimitService;
use Tests\TestCase;

class ConsolidatedTransactionDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_company_transaction(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $consolidated = Consolidated::factory()->forAccount($account)->create([
            'quantity_current' => 6,
            'quantity_purchased' => 10,
            'quantity_sold' => 4,
            'total_purchased' => 100,
            'total_sold' => 60,
            'average_purchase_price' => 10,
            'average_selling_price' => 15,
            'closed' => false,
        ]);

        $buyTransaction = CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'C',
            'quantity' => 10,
            'price' => 10,
            'total_value' => 100,
        ]);

        $sellTransaction = CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'V',
            'quantity' => 4,
            'price' => 15,
            'total_value' => 60,
        ]);

        $response = $this->deleteJson(
            "/api/consolidated/transactions/company/{$sellTransaction->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('company_transactions', ['id' => $sellTransaction->id]);
        $this->assertDatabaseHas('company_transactions', ['id' => $buyTransaction->id]);

        $consolidated->refresh();
        $this->assertEquals('10.00000000', (string) $consolidated->quantity_current);
        $this->assertEquals('10.00000000', (string) $consolidated->quantity_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->quantity_sold);
        $this->assertEquals('100.00000000', (string) $consolidated->total_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->total_sold);
        $this->assertEquals('10.00000000', (string) $consolidated->average_purchase_price);
        $this->assertEquals('0.00000000', (string) $consolidated->average_selling_price);
        $this->assertFalse($consolidated->closed);
    }

    public function test_can_delete_treasure_transaction(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $treasure = \App\Models\Treasure::factory()->create();

        $consolidated = Consolidated::factory()->forAccount($account)->create([
            'treasure_id' => $treasure->id,
            'quantity_current' => 6,
            'quantity_purchased' => 10,
            'quantity_sold' => 4,
            'total_purchased' => 200,
            'total_sold' => 120,
            'average_purchase_price' => 20,
            'average_selling_price' => 30,
            'closed' => false,
        ]);

        $buyTransaction = \App\Models\TreasureTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'C',
            'quantity' => 10,
            'invested_value' => 200,
            'price' => 20,
        ]);

        $sellTransaction = \App\Models\TreasureTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'V',
            'quantity' => 4,
            'invested_value' => 120,
            'price' => 30,
        ]);

        $response = $this->deleteJson(
            "/api/consolidated/transactions/treasure/{$sellTransaction->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('treasure_transaction', ['id' => $sellTransaction->id]);
        $this->assertDatabaseHas('treasure_transaction', ['id' => $buyTransaction->id]);

        $consolidated->refresh();
        $this->assertEquals('10.00000000', (string) $consolidated->quantity_current);
        $this->assertEquals('10.00000000', (string) $consolidated->quantity_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->quantity_sold);
        $this->assertEquals('200.00000000', (string) $consolidated->total_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->total_sold);
        $this->assertEquals('20.00000000', (string) $consolidated->average_purchase_price);
        $this->assertEquals('0.00000000', (string) $consolidated->average_selling_price);
        $this->assertFalse($consolidated->closed);
    }

    public function test_can_delete_transaction_outside_edit_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $subscription = $auth['user']->activeSubscription()->first();
        $limits = $subscription->limits_snapshot ?? [];
        $limits['max_positions'] = 1;
        $subscription->update(['limits_snapshot' => $limits]);

        $oldConsolidated = Consolidated::factory()->forAccount($account)->create([
            'created_at' => now()->subDays(2),
            'quantity_current' => 1,
            'quantity_purchased' => 1,
            'total_purchased' => 10,
            'average_purchase_price' => 10,
        ]);
        $newConsolidated = Consolidated::factory()->forAccount($account)->create([
            'created_at' => now(),
            'quantity_current' => 1,
            'quantity_purchased' => 1,
            'total_purchased' => 20,
            'average_purchase_price' => 20,
        ]);

        $service = app(SubscriptionLimitService::class);
        $this->assertFalse($service->canEditPosition($auth['user'], $newConsolidated));

        $transaction = CompanyTransaction::factory()->create([
            'consolidated_id' => $newConsolidated->id,
            'operation' => 'C',
            'quantity' => 1,
            'price' => 20,
            'total_value' => 20,
        ]);

        $response = $this->deleteJson(
            "/api/consolidated/transactions/company/{$transaction->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('company_transactions', ['id' => $transaction->id]);
        $this->assertDatabaseHas('consolidated', ['id' => $oldConsolidated->id]);
    }
}
