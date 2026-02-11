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

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Provento removido com sucesso.',
            ]);
        $this->assertDatabaseMissing('earnings', ['id' => $earning->id]);
    }

    public function test_cannot_delete_other_user_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAuth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create(['user_id' => $otherAuth['user']->id]);
        $otherConsolidated = Consolidated::factory()->forAccount($otherAccount)->create();
        $earning = Earning::factory()->forConsolidated($otherConsolidated)->create();

        $response = $this->deleteJson(
            "/api/earnings/{$earning->id}",
            [],
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Provento nao encontrado.',
            ]);

        $this->assertDatabaseHas('earnings', ['id' => $earning->id]);
    }

    public function test_cannot_delete_earning_without_authentication(): void
    {
        $account = Account::factory()->create();
        $consolidated = Consolidated::factory()->forAccount($account)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create();

        $response = $this->deleteJson("/api/earnings/{$earning->id}");

        $response->assertStatus(401);

        $this->assertDatabaseHas('earnings', ['id' => $earning->id]);
    }
}
