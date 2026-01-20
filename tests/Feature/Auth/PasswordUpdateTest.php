<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    public function test_can_update_password(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Senha atualizada com sucesso.',
            ]);

        $auth['user']->refresh();
        $this->assertTrue(Hash::check('new-password-123', $auth['user']->password));
    }

    public function test_cannot_update_password_with_invalid_current_password(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->putJson('/api/auth/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422);
    }

    public function test_cannot_update_password_without_authentication(): void
    {
        $response = $this->putJson('/api/auth/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ]);

        $response->assertStatus(401);
    }
}
