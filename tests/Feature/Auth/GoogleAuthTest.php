<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_login_with_valid_google_token(): void
    {
        $mockGoogleData = [
            'google_id' => '123456789',
            'email' => 'test@example.com',
            'name' => 'Test User',
            'photo' => 'https://example.com/photo.jpg',
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturn(User::factory()->create([
                'google_id' => $mockGoogleData['google_id'],
                'email' => $mockGoogleData['email'],
                'name' => $mockGoogleData['name'],
            ]));

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => str_repeat('a', 120),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'photo',
                        'status',
                    ],
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token_type' => 'Bearer',
                ],
                'message' => 'Login realizado com sucesso.',
            ]);
    }

    public function test_cannot_login_with_invalid_google_token(): void
    {
        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn(null);

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => str_repeat('b', 120),
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Token do Google invalido ou expirado.',
            ]);
    }

    public function test_cannot_login_without_id_token(): void
    {
        $response = $this->postJson('/api/auth/google', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_token']);
    }

    public function test_cannot_login_with_empty_id_token(): void
    {
        $response = $this->postJson('/api/auth/google', [
            'id_token' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['id_token']);
    }

    public function test_cannot_login_when_user_is_inactive(): void
    {
        $inactiveUser = User::factory()->inactive()->create();

        $mockGoogleData = [
            'google_id' => $inactiveUser->google_id,
            'email' => $inactiveUser->email,
            'name' => $inactiveUser->name,
            'photo' => $inactiveUser->photo,
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturn($inactiveUser);

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => str_repeat('c', 120),
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Sua conta esta desativada. Entre em contato com o suporte.',
            ]);
    }

    public function test_creates_new_user_on_first_login(): void
    {
        $mockGoogleData = [
            'google_id' => 'new_google_id_123',
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'photo' => 'https://example.com/new-photo.jpg',
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')
            ->once()
            ->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')
            ->once()
            ->andReturnUsing(function ($data) {
                return User::create([
                    'google_id' => $data['google_id'],
                    'email' => $data['email'],
                    'name' => $data['name'],
                    'photo' => $data['photo'],
                    'email_verified_at' => now(),
                    'status' => true,
                    'password' => Hash::make('password'),
                ]);
            });

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => str_repeat('d', 120),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'google_id' => 'new_google_id_123',
        ]);
    }

    public function test_revokes_previous_tokens_on_login(): void
    {
        $user = User::factory()->create();

        $oldToken = $user->createToken('old-token')->plainTextToken;

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $mockGoogleData = [
            'google_id' => $user->google_id,
            'email' => $user->email,
            'name' => $user->name,
            'photo' => $user->photo,
            'email_verified' => true,
        ];

        $mock = Mockery::mock(GoogleAuthService::class);
        $mock->shouldReceive('verifyIdToken')->andReturn($mockGoogleData);
        $mock->shouldReceive('findOrCreateUser')->andReturn($user);

        $this->app->instance(GoogleAuthService::class, $mock);

        $response = $this->postJson('/api/auth/google', [
            'id_token' => str_repeat('e', 120),
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->getJson('/api/auth/me', [
            'Authorization' => 'Bearer ' . $oldToken,
        ])->assertStatus(401);
    }
}
