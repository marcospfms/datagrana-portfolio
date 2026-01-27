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
            $this->validateWebhookSignature($request);
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

    private function validateWebhookSignature(Request $request): void
    {
        $signature = $request->header('X-RevenueCat-Signature');
        $webhookSecret = config('services.revenuecat.webhook_secret');

        if (!$signature || !$webhookSecret) {
            throw new \Exception('Missing webhook signature or secret');
        }

        $payload = $request->getContent();
        $computedSignature = hash_hmac('sha256', $payload, $webhookSecret);

        if (!hash_equals($computedSignature, $signature)) {
            throw new \Exception('Invalid webhook signature');
        }
    }
}
