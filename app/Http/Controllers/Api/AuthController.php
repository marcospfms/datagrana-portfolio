<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Auth\GoogleAuthRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\GoogleAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends BaseController
{
    public function __construct(
        protected GoogleAuthService $googleAuthService
    ) {}

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
