<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\SubscriptionPlanResource;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;

class SubscriptionPlanController extends BaseController
{
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::where('is_active', true)
            ->with('configs')
            ->orderBy('display_order')
            ->get();

        return $this->sendResponse(
            SubscriptionPlanResource::collection($plans),
            'Planos de assinatura carregados com sucesso.'
        );
    }

    public function show(SubscriptionPlan $plan): JsonResponse
    {
        $plan->load('configs');

        return $this->sendResponse(
            new SubscriptionPlanResource($plan),
            'Plano carregado com sucesso.'
        );
    }
}
