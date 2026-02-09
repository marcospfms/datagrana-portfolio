<?php

namespace Tests\Feature\Earning;

use App\Models\Account;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Models\EarningType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_earning(): void
    {
        $auth = $this->createAuthenticatedUser();
        $type = EarningType::factory()->create();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $consolidated = Consolidated::factory()->forAccount($account)->create();

        $payload = [
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $type->id,
            'date' => now()->toDateTimeString(),
            'quantity' => 10,
            'net_value' => 50.25,
            'gross_value' => 60.25,
            'tax' => 10,
            'imported_with' => 'Manual',
        ];

        $response = $this->postJson('/api/earnings', $payload, $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.consolidated_id', $consolidated->id);

        $this->assertDatabaseHas('earnings', [
            'consolidated_id' => $consolidated->id,
            'earning_type_id' => $type->id,
        ]);
    }

    public function test_cannot_create_earning_for_other_user(): void
    {
        $auth = $this->createAuthenticatedUser();
        $type = EarningType::factory()->create();
        $otherAccount = Account::factory()->create();
        $otherConsolidated = Consolidated::factory()->forAccount($otherAccount)->create();

        $payload = [
            'consolidated_id' => $otherConsolidated->id,
            'earning_type_id' => $type->id,
            'date' => now()->toDateTimeString(),
            'quantity' => 10,
            'net_value' => 50.25,
        ];

        $response = $this->postJson('/api/earnings', $payload, $this->authHeaders($auth['token']));

        $response->assertStatus(404);

        $this->assertDatabaseMissing('earnings', [
            'consolidated_id' => $otherConsolidated->id,
        ]);
    }

    public function test_create_validation_fails(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/earnings', [], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['consolidated_id', 'earning_type_id', 'date', 'quantity', 'net_value']);
    }
}
