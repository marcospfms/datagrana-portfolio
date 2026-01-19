<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Bank;
use Tests\TestCase;

class AccountStoreTest extends TestCase
{
    public function test_can_create_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $bank = Bank::factory()->create();

        $response = $this->postJson('/api/accounts', [
            'bank_id' => $bank->id,
            'account' => '123456-7',
            'nickname' => 'Minha conta',
            'default' => false,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conta criada com sucesso.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'account',
                    'nickname',
                    'default',
                    'bank',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_id' => $auth['user']->id,
            'bank_id' => $bank->id,
            'account' => '123456-7',
        ]);
    }

    public function test_first_account_is_automatically_default(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'account' => '123456-7',
            'default' => false,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.default'));
    }

    public function test_setting_new_default_removes_old_default(): void
    {
        $auth = $this->createAuthenticatedUser();

        $oldDefault = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
        ]);

        $response = $this->postJson('/api/accounts', [
            'account' => '999999-9',
            'default' => true,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.default'));
        $this->assertFalse($oldDefault->fresh()->default);
    }

    public function test_cannot_create_duplicate_account_number(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'account' => '123456-7',
        ]);

        $response = $this->postJson('/api/accounts', [
            'account' => '123456-7',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }

    public function test_can_create_account_without_bank(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'account' => '123456-7',
            'nickname' => 'Conta sem banco',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertNull($response->json('data.bank_id'));
    }

    public function test_cannot_create_account_with_inactive_bank(): void
    {
        $auth = $this->createAuthenticatedUser();
        $bank = Bank::factory()->inactive()->create();

        $response = $this->postJson('/api/accounts', [
            'bank_id' => $bank->id,
            'account' => '123456-7',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_id']);
    }

    public function test_account_number_is_required(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/accounts', [
            'nickname' => 'Conta',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }
}
