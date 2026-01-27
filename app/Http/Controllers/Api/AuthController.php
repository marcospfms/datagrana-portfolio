<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\GoogleAuthService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends BaseController
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (!$user || !Hash::check($request->validated('password'), $user->password)) {
            return $this->sendError('Credenciais invalidas.', [
                'email' => ['As credenciais fornecidas sao invalidas.'],
            ], 401);
        }

        if (!$user->isActive()) {
            return $this->sendError(
                'Sua conta esta desativada. Entre em contato com o suporte.',
                [],
                403
            );
        }

        app(SubscriptionLimitService::class)->ensureUserHasSubscription($user);

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login realizado com sucesso.');
    }

    public function google(GoogleAuthRequest $request): JsonResponse
    {
        $googleData = $this->googleAuthService->verifyIdToken($request->id_token);

        if (!$googleData) {
            return $this->sendError('Token do Google invalido ou expirado.', [], 401);
        }

        $user = $this->googleAuthService->findOrCreateUser($googleData);

        if (!$user->isActive()) {
            return $this->sendError(
                'Sua conta esta desativada. Entre em contato com o suporte.',
                [],
                403
            );
        }

        app(SubscriptionLimitService::class)->ensureUserHasSubscription($user);

        $user->tokens()->delete();
        $token = $user->createToken('mobile-app')->plainTextToken;

        return $this->sendResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login realizado com sucesso.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function profile(Request $request): JsonResponse
    {
        return $this->sendResponse([
            'user' => new UserResource($request->user()),
        ], 'Perfil carregado com sucesso.');
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $this->sendResponse([
            'user' => new UserResource($user),
        ], 'Perfil atualizado com sucesso.');
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        return $this->sendResponse([], 'Senha atualizada com sucesso.');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->sendResponse([], 'Logout realizado com sucesso.');
    }

    public function logoutAll(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return $this->sendResponse([], 'Logout de todos os dispositivos realizado.');
    }
}
