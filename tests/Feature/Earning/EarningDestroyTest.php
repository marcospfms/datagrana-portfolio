<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Consolidated;
use App\Models\Earning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningDestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_delete_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create();

        $response = $this->deleteJson(
            "/api/earnings/{$earning->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200);
        $this->assertDatabaseMissing('earnings', ['id' => $earning->id]);
    }
}
