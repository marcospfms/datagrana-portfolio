<?php

namespace Tests\Feature\Consolidated;

use App\Models\Account;
use App\Models\Consolidated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsolidatedShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_view_own_position(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $position = Consolidated::factory()->forAccount($account)->create([
            'average_purchase_price' => 40.00,
            'quantity_current' => 100,
            'total_purchased' => 4000.00,
        ]);
        $position->companyTicker()->update(['last_price' => 50.00]);

        $response = $this->getJson(
            "/api/consolidated/{$position->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $position->id)
            ->assertJsonPath('data.balance', '5000')
            ->assertJsonPath('data.profit', '1000')
            ->assertJsonPath('data.profit_percentage', '25');
    }

    public function test_cannot_view_other_user_position(): void
    {
        $auth = $this->createAuthenticatedUser();
        $position = Consolidated::factory()->create();

        $response = $this->getJson(
            "/api/consolidated/{$position->id}",
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(403);
    }

    public function test_returns_404_for_nonexistent_position(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/consolidated/99999', $this->authHeaders($auth['token']));

        $response->assertStatus(404);
    }
}
