<?php

namespace App\Services\Auth;

use App\Models\User;
use Google_Client;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleAuthService
{
    protected Google_Client $client;

    public function __construct()
    {
        $this->client = new Google_Client([
            'client_id' => config('services.google.client_id'),
        ]);
    }

    public function verifyIdToken(string $idToken): ?array
    {
        try {
            $payload = $this->client->verifyIdToken($idToken);

            if (!$payload) {
                Log::warning('Google token verification returned empty payload.');
                return null;
            }

            return [
                'google_id' => $payload['sub'],
                'email' => $payload['email'],
                'name' => $payload['name'] ?? null,
                'photo' => $payload['picture'] ?? null,
                'email_verified' => $payload['email_verified'] ?? false,
            ];
        } catch (\Throwable $exception) {
            Log::error('Google token verification failed.', [
                'error' => $exception->getMessage(),
            ]);
            return null;
        }
    }

    public function findOrCreateUser(array $googleData): User
    {
        $user = User::query()
            ->where('google_id', $googleData['google_id'])
            ->orWhere('email', $googleData['email'])
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleData['google_id'],
                'photo' => $googleData['photo'] ?? $user->photo,
                'name' => $googleData['name'] ?? $user->name,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);

            Log::info('User updated via Google OAuth.', ['user_id' => $user->id]);
        } else {
            $user = User::create([
                'google_id' => $googleData['google_id'],
                'email' => $googleData['email'],
                'name' => $googleData['name'] ?? 'Usuario',
                'photo' => $googleData['photo'],
                'email_verified_at' => now(),
                'status' => true,
                'password' => Hash::make(Str::random(32)),
            ]);

            Log::info('New user created via Google OAuth.', ['user_id' => $user->id]);
        }

        return $user;
    }
}
