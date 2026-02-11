<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Models\EarningType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_update_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $type = EarningType::factory()->create();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        $earning = Earning::factory()->forConsolidated($consolidated)->create([
            'earning_type_id' => $type->id,
            'net_value' => 10,
        ]);

        $payload = [
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $type->id,
            'date' => now()->toDateTimeString(),
            'quantity' => 5,
            'net_value' => 99.5,
            'gross_value' => 120,
            'tax' => 20.5,
        ];

        $response = $this->putJson(
            "/api/earnings/{$earning->id}",
            $payload,
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(200)
            ->assertJsonPath('data.net_value', '99.50000000');
    }

    public function test_cannot_update_other_user_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAuth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create(['user_id' => $otherAuth['user']->id]);
        $otherConsolidated = Consolidated::factory()->forAccount($otherAccount)->create();
        $earning = Earning::factory()->forConsolidated($otherConsolidated)->create();

        $type = EarningType::factory()->create();
        $payload = [
            'consolidated_id' => $otherConsolidated->id,
            'earning_type_id' => $type->id,
            'date' => now()->toDateTimeString(),
            'quantity' => 5,
            'net_value' => 99.5,
        ];

        $response = $this->putJson(
            "/api/earnings/{$earning->id}",
            $payload,
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Provento nao encontrado.',
            ]);
    }

    public function test_cannot_update_earning_without_authentication(): void
    {
        $account = Account::factory()->create();
        $consolidated = Consolidated::factory()->forAccount($account)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create();

        $type = EarningType::factory()->create();
        $payload = [
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $type->id,
            'date' => now()->toDateTimeString(),
            'quantity' => 5,
            'net_value' => 99.5,
        ];

        $response = $this->putJson(
            "/api/earnings/{$earning->id}",
            $payload
        );

        $response->assertStatus(401);
    }

    public function test_cannot_update_with_invalid_consolidated_id(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();
        $earning = Earning::factory()->forConsolidated($consolidated)->create();

        $otherAuth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create(['user_id' => $otherAuth['user']->id]);
        $otherConsolidated = Consolidated::factory()->forAccount($otherAccount)->create();

        $type = EarningType::factory()->create();
        $payload = [
            'consolidated_id' => $otherConsolidated->id,
            'earning_type_id' => $type->id,
            'date' => now()->toDateTimeString(),
            'quantity' => 5,
            'net_value' => 99.5,
        ];

        $response = $this->putJson(
            "/api/earnings/{$earning->id}",
            $payload,
            $this->authHeaders($auth['token'])
        );

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Posicao consolidada nao encontrada.',
            ]);
    }
}
