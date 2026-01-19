<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use Tests\TestCase;

class AccountDestroyTest extends TestCase
{
    public function test_can_delete_own_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->deleteJson("/api/accounts/{$account->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conta removida com sucesso.',
            ]);

        $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
    }

    public function test_cannot_delete_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();

        $response = $this->deleteJson("/api/accounts/{$otherAccount->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
        $this->assertDatabaseHas('accounts', ['id' => $otherAccount->id]);
    }

    public function test_deleting_default_assigns_new_default(): void
    {
        $auth = $this->createAuthenticatedUser();

        $defaultAccount = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
            'created_at' => now()->subDay(),
        ]);
        $otherAccount = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => false,
            'created_at' => now(),
        ]);

        $response = $this->deleteJson("/api/accounts/{$defaultAccount->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($otherAccount->fresh()->default);
    }

    public function test_can_delete_last_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
        ]);

        $response = $this->deleteJson("/api/accounts/{$account->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertDatabaseCount('accounts', 0);
    }
}
