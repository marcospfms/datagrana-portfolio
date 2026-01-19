<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use Tests\TestCase;

class AccountShowTest extends TestCase
{
    public function test_can_view_own_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $response = $this->getJson("/api/accounts/{$account->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $account->id,
                    'account' => $account->account,
                ],
            ]);
    }

    public function test_cannot_view_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();

        $response = $this->getJson("/api/accounts/{$otherAccount->id}", $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    public function test_returns_404_for_nonexistent_account(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/accounts/99999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }
}
