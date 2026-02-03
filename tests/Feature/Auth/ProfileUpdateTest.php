<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    public function test_can_get_profile(): void
    {
        $auth = $this->createAuthenticatedUser([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);

        $response = $this->getJson('/api/auth/profile', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'Jane Doe')
            ->assertJsonPath('data.user.email', 'jane@example.com');
    }

    public function test_can_update_profile(): void
    {
        $auth = $this->createAuthenticatedUser([
            'email' => 'john@example.com',
            'google_id' => null,
        ]);

        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonPath('data.user.name', 'John Updated')
            ->assertJsonPath('data.user.email', 'john.updated@example.com')
            ->assertJsonPath('data.user.email_verified_at', null);
    }

    public function test_cannot_update_profile_without_authentication(): void
    {
        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_update_profile_with_invalid_email(): void
    {
        $auth = $this->createAuthenticatedUser();

        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'John Updated',
            'email' => 'not-an-email',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422);
    }

    public function test_cannot_update_profile_with_existing_email(): void
    {
        $existing = $this->createAuthenticatedUser([
            'email' => 'existing@example.com',
        ]);
        $auth = $this->createAuthenticatedUser([
            'email' => 'john@example.com',
        ]);

        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'John Updated',
            'email' => $existing['user']->email,
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(422);
    }

    public function test_cannot_update_profile_when_user_is_google_account(): void
    {
        $auth = $this->createAuthenticatedUser([
            'google_id' => '1234567890',
        ]);

        $response = $this->patchJson('/api/auth/profile', [
            'name' => 'John Updated',
            'email' => 'john.updated@example.com',
        ], $this->authHeaders($auth['token']));

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Conta Google não permite atualização de perfil.');
    }
}
