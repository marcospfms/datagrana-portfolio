<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Consolidated;
use App\Models\Earning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_list_own_earnings(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        Earning::factory()->count(2)->forConsolidated($consolidated)->create();
        Earning::factory()->count(1)->create();

        $response = $this->getJson('/api/earnings', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_cannot_list_earnings_without_authentication(): void
    {
        $response = $this->getJson('/api/earnings');

        $response->assertStatus(401);
    }
}
