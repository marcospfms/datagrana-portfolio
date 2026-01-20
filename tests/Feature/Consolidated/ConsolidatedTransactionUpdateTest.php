<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedTransactionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_company_transaction(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $consolidated = Consolidated::factory()->forAccount($account)->create([
            'quantity_current' => 10,
            'quantity_purchased' => 10,
            'total_purchased' => 100,
        ]);

        $transaction = CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'C',
            'quantity' => 10,
            'price' => 10,
            'total_value' => 100,
        ]);

        $response = $this->putJson("/api/consolidated/transactions/company/{$transaction->id}", [
            'account_id' => $account->id,
            'type' => 'company',
            'date' => now()->toDateTimeString(),
            'operation' => 'buy',
            'quantity' => 5,
            'price' => 10,
            'company_ticker_id' => $consolidated->company_ticker_id,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $consolidated->refresh();
        $this->assertEquals('5.00000000', (string) $consolidated->quantity_current);
        $this->assertEquals('5.00000000', (string) $consolidated->quantity_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->quantity_sold);
        $this->assertEquals('50.00000000', (string) $consolidated->total_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->total_sold);
        $this->assertEquals('10.00000000', (string) $consolidated->average_purchase_price);
        $this->assertEquals('0.00000000', (string) $consolidated->average_selling_price);
        $this->assertFalse($consolidated->closed);
    }

    public function test_can_update_treasure_transaction(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $treasure = \App\Models\Treasure::factory()->create();

        $consolidated = Consolidated::factory()->forAccount($account)->create([
            'treasure_id' => $treasure->id,
            'quantity_current' => 10,
            'quantity_purchased' => 10,
            'total_purchased' => 200,
        ]);

        $transaction = \App\Models\TreasureTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'C',
            'quantity' => 10,
            'invested_value' => 200,
            'price' => 20,
        ]);

        $response = $this->putJson("/api/consolidated/transactions/treasure/{$transaction->id}", [
            'account_id' => $account->id,
            'type' => 'treasure',
            'date' => now()->toDateTimeString(),
            'operation' => 'buy',
            'quantity' => 5,
            'invested_value' => 100,
            'treasure_id' => $treasure->id,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $consolidated->refresh();
        $this->assertEquals('5.00000000', (string) $consolidated->quantity_current);
        $this->assertEquals('5.00000000', (string) $consolidated->quantity_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->quantity_sold);
        $this->assertEquals('100.00000000', (string) $consolidated->total_purchased);
        $this->assertEquals('0.00000000', (string) $consolidated->total_sold);
        $this->assertEquals('20.00000000', (string) $consolidated->average_purchase_price);
        $this->assertEquals('0.00000000', (string) $consolidated->average_selling_price);
        $this->assertFalse($consolidated->closed);
    }
}
