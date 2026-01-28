<?php

namespace Tests\Feature\Account;

use App\Models\Account;
use App\Models\Bank;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AccountUpdateTest extends TestCase
{
    public function test_can_update_own_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $account = Account::factory()->create(['user_id' => $auth['user']->id]);
        $newBank = Bank::factory()->create();

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'bank_id' => $newBank->id,
            'nickname' => 'Novo apelido',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Conta atualizada com sucesso.',
                'data' => [
                    'nickname' => 'Novo apelido',
                    'bank_id' => $newBank->id,
                ],
            ]);
    }

    public function test_cannot_update_other_user_account(): void
    {
        $auth = $this->createAuthenticatedUser();
        $otherAccount = Account::factory()->create();

        $response = $this->putJson("/api/accounts/{$otherAccount->id}", [
            'nickname' => 'Hacked',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403);
    }

    public function test_setting_default_removes_other_defaults(): void
    {
        $auth = $this->createAuthenticatedUser();
        $baseDate = Carbon::now()->subDays(2);

        $oldDefault = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => true,
            'created_at' => $baseDate->copy()->addDay(),
        ]);
        $account = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'default' => false,
            'created_at' => $baseDate->copy(),
        ]);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'default' => true,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200);
        $this->assertTrue($response->json('data.default'));
        $this->assertFalse($oldDefault->fresh()->default);
    }

    public function test_cannot_duplicate_account_number_on_update(): void
    {
        $auth = $this->createAuthenticatedUser();

        Account::factory()->create([
            'user_id' => $auth['user']->id,
            'account' => '111111-1',
        ]);
        $account = Account::factory()->create([
            'user_id' => $auth['user']->id,
            'account' => '222222-2',
        ]);

        $response = $this->putJson("/api/accounts/{$account->id}", [
            'account' => '111111-1',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account']);
    }

    public function test_cannot_update_account_outside_limit(): void
    {
        $auth = $this->createAuthenticatedUser();
        $baseDate = Carbon::now()->subDays(5);

        $accounts = Account::factory()
            ->count(2)
            ->state(['user_id' => $auth['user']->id])
            ->sequence(fn ($sequence) => [
                'created_at' => $baseDate->copy()->addDays($sequence->index),
            ])
            ->create();

        $oldest = $accounts->first();
        $newest = $accounts->last();

        $blockedResponse = $this->putJson("/api/accounts/{$newest->id}", [
            'nickname' => 'Bloqueada',
        ], $this->authHeaders($auth['token']));

        $blockedResponse->assertStatus(403);

        $allowedResponse = $this->putJson("/api/accounts/{$oldest->id}", [
            'nickname' => 'Permitida',
        ], $this->authHeaders($auth['token']));

        $allowedResponse->assertStatus(200)
            ->assertJsonPath('data.nickname', 'Permitida');
    }
}
