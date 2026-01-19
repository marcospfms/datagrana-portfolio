<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    public function test_can_logout_current_device(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->postJson('/api/auth/logout', [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout realizado com sucesso.',
            ]);

        // Limpa cache de autenticação do Laravel
        Auth::forgetGuards();

        // Token deve estar revogado
        $this->getJson('/api/auth/me', $this->authHeaders($auth['token']))
            ->assertStatus(401);
    }

    public function test_can_logout_all_devices(): void
    {
        $auth = $this->createAuthenticatedUser();

        $token2 = $auth['user']->createToken('device-2')->plainTextToken;
        $token3 = $auth['user']->createToken('device-3')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 3);

        $response = $this->postJson('/api/auth/logout-all', [], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logout de todos os dispositivos realizado.',
            ]);

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Limpa cache de autenticação do Laravel
        Auth::forgetGuards();

        // Todos os tokens devem estar revogados
        $this->getJson('/api/auth/me', $this->authHeaders($auth['token']))
            ->assertStatus(401);
        $this->getJson('/api/auth/me', $this->authHeaders($token2))
            ->assertStatus(401);
        $this->getJson('/api/auth/me', $this->authHeaders($token3))
            ->assertStatus(401);
    }

    public function test_cannot_logout_without_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }

    public function test_cannot_logout_all_without_authentication(): void
    {
        $response = $this->postJson('/api/auth/logout-all');

        $response->assertStatus(401);
    }
}
