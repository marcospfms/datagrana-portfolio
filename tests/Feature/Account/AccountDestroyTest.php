<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Services\SubscriptionLimitService;
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

    public function test_can_delete_account_outside_edit_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $oldAccount = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'created_at' => now()->subDays(2),
        ]);
        $newAccount = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'created_at' => now(),
        ]);

        $limitService = app(SubscriptionLimitService::class);
        $this->assertFalse($limitService->canEditAccount($auth['user'], $newAccount));

        $response = $this->deleteJson("/api/accounts/{$newAccount->id}", [], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertDatabaseMissing('accounts', ['id' => $newAccount->id]);
        $this->assertDatabaseHas('accounts', ['id' => $oldAccount->id]);
    }
}
