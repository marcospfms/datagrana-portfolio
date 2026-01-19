<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
