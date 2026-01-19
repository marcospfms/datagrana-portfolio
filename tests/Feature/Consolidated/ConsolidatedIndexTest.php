<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_own_positions(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->count(3)->forAccount($account)->create();
        Consolidated::factory()->count(2)->create();

        $response = $this->getJson('/api/consolidated', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_by_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account1 = Account::factory()->create(['user_id' => $auth['user']->id]);
        $account2 = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->count(2)->forAccount($account1)->create();
        Consolidated::factory()->count(3)->forAccount($account2)->create();

        $response = $this->getJson(
            "/api/consolidated?account_id={$account1->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_can_filter_by_closed_status(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        Consolidated::factory()->count(2)->forAccount($account)->create(['closed' => false]);
        Consolidated::factory()->count(1)->forAccount($account)->create(['closed' => true]);

        $response = $this->getJson(
            '/api/consolidated?closed=0',
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_list_positions_without_authentication(): void
    {
        $response = $this->getJson('/api/consolidated');

        $response->assertStatus(401);
    }
}
