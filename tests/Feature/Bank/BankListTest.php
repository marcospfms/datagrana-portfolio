<?php

namespace Tests\Feature\Bank;

use App\Models\Bank;
use Tests\TestCase;

class BankListTest extends TestCase
{
    public function test_can_list_active_banks(): void
    {
        Bank::factory()->count(3)->create(['status' => true]);
        Bank::factory()->count(2)->create(['status' => false]);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/banks', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'nickname',
                        'cnpj',
                        'photo',
                        'status',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_list_banks_without_authentication(): void
    {
        $response = $this->getJson('/api/banks');

        $response->assertStatus(401);
    }

    public function test_banks_are_ordered_by_name(): void
    {
        Bank::factory()->create(['name' => 'Zebra Corretora']);
        Bank::factory()->create(['name' => 'Alpha Corretora']);
        Bank::factory()->create(['name' => 'Beta Corretora']);

        $auth = $this->createAuthenticatedUser();

        $response = $this->getJson('/api/banks', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha Corretora', 'Beta Corretora', 'Zebra Corretora'], $names);
    }
}
