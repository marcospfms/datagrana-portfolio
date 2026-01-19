<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use Tests\TestCase;

class AccountIndexTest extends TestCase
{
    public function test_can_list_own_accounts(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->count(3)->create(['user_id' => $auth['user']->id]);
        Account::factory()->count(2)->create();

        $response = $this->getJson('/api/accounts', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'user_id',
                        'bank_id',
                        'account',
                        'nickname',
                        'default',
                        'bank',
                    ],
                ],
            ])
            ->assertJsonCount(3, 'data');
    }

    public function test_cannot_list_accounts_without_authentication(): void
    {
        $response = $this->getJson('/api/accounts');

        $response->assertStatus(401);
    }

    public function test_default_account_comes_first(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'nickname' => 'Normal',
            'default' => false,
        ]);
        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'nickname' => 'Default',
            'default' => true,
        ]);

        $response = $this->getJson('/api/accounts', $this->authHeaders($auth['token']));

        $response->assertStatus(200);

        $firstAccount = $response->json('data.0');
        $this->assertEquals('Default', $firstAccount['nickname']);
        $this->assertTrue($firstAccount['default']);
    }
}
