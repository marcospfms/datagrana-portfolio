<?php

namespace Tests\Feature\Auth;

use Tests\TestCase;

class MeEndpointTest extends TestCase
{
    public function test_can_get_authenticated_user_data(): void
    {
        $auth = $this->createAuthenticatedUser([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $response = $this->getJson('/api/auth/me', $this->authHeaders($auth['token']));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'photo',
                        'status',
                        'email_verified_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'name' => 'John Doe',
                        'email' => 'john@example.com',
                    ],
                ],
            ]);
    }

    public function test_cannot_get_user_data_without_token(): void
    {
        $response = $this->getJson('/api/auth/me');

        $response->assertStatus(401);
    }

    public function test_cannot_get_user_data_with_invalid_token(): void
    {
        $response = $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer invalid_token_here',
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_get_user_data_with_revoked_token(): void
    {
        $auth = $this->createAuthenticatedUser();

        $auth['user']->tokens()->delete();

        $response = $this->getJson('/api/auth/me', $this->authHeaders($auth['token']));

        $response->assertStatus(401);
    }
}
