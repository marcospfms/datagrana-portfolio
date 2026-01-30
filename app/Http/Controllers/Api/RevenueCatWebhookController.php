<?php

namespace App\Http\Controllers\Api;

use App\Services\RevenueCatWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RevenueCatWebhookController extends BaseController
{
    public function __construct(
        protected RevenueCatWebhookService $webhookService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $this->validateAuthorizationHeader($request);
            $this->webhookService->processWebhook($request->all());

            return $this->sendResponse(['status' => 'success'], 'Webhook processado com sucesso.');
        } catch (\Exception $exception) {
            $payload = $request->all();

            \Log::error('RevenueCat Webhook Error: ' . $exception->getMessage(), [
                'event_type' => $payload['event']['type'] ?? null,
                'app_user_id' => $payload['event']['app_user_id'] ?? null,
                'subscriber_id' => $payload['event']['subscriber_id'] ?? null,
                'exception' => $exception,
            ]);

            return $this->sendError('Erro ao processar webhook.', [], 500);
        }
    }

    private function validateAuthorizationHeader(Request $request): void
    {
        $expected = config('services.revenuecat.webhook_auth_header');

        if (!$expected) {
            return;
        }

        $authorization = $request->header('Authorization');

        if ($authorization !== $expected) {
            throw new \Exception('Invalid webhook authorization header');
        }
    }
}
