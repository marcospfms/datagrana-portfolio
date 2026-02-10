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
        Consolidated::factory()->forAccount($account)->closed()->create();
        Consolidated::factory()->count(2)->create();

        $response = $this->getJson('/api/consolidated', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_can_filter_by_search(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);

        $first = Consolidated::factory()->forAccount($account)->create();
        $second = Consolidated::factory()->forAccount($account)->create();

        $first->companyTicker()->update(['code' => 'AAAA3']);
        $second->companyTicker()->update(['code' => 'BBBB4']);

        $response = $this->getJson(
            '/api/consolidated?search=AAAA',
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_ticker.code', 'AAAA3');
    }

    public function test_index_validates_search_param(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson(
            '/api/consolidated?search=' . str_repeat('A', 101),
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['search']);
    }

    public function test_cannot_list_positions_without_authentication(): void
    {
        $response = $this->getJson('/api/consolidated');

        $response->assertStatus(401);
    }
}
