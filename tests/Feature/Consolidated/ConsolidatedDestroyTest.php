<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use Tests\TestCase;

class ConsolidatedDestroyTest extends TestCase
{
    public function test_can_delete_consolidated_and_transactions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $consolidated = Consolidated::factory()->forAccount($account)->create([
            'quantity_current' => 2,
            'quantity_purchased' => 2,
            'total_purchased' => 20,
            'average_purchase_price' => 10,
        ]);

        $transaction = CompanyTransaction::factory()->create([
            'consolidated_id' => $consolidated->id,
            'operation' => 'C',
            'quantity' => 2,
            'price' => 10,
            'total_value' => 20,
        ]);

        $response = $this->deleteJson(
            "/api/consolidated/{$consolidated->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Posição removida com sucesso.',
            ]);

        $this->assertDatabaseMissing('consolidated', ['id' => $consolidated->id]);
        $this->assertDatabaseMissing('company_transactions', ['id' => $transaction->id]);
    }

    public function test_cannot_delete_other_user_consolidated(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create();
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        $response = $this->deleteJson(
            "/api/consolidated/{$consolidated->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
        $this->assertDatabaseHas('consolidated', ['id' => $consolidated->id]);
    }

    public function test_cannot_delete_consolidated_without_authentication(): void
    {
        $account = Account::factory()->create();
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        $response = $this->deleteJson("/api/consolidated/{$consolidated->id}");

        $response->assertStatus(401);
        $this->assertDatabaseHas('consolidated', ['id' => $consolidated->id]);
    }
}
