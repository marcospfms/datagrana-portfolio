<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\UserSubscriptionResource;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSubscriptionController extends BaseController
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $subscription = $user->subscriptions()->active()->with('usage')->first();

        if (!$subscription) {
            $subscription = $this->limitService->createFreeSubscription($user);
            $subscription->load('usage');
        }

        return $this->sendResponse(
            new UserSubscriptionResource($subscription),
            'Assinatura atual carregada com sucesso.'
        );
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(
            UserSubscriptionResource::collection($subscriptions),
            'Historico de assinaturas carregado com sucesso.'
        );
    }
}
