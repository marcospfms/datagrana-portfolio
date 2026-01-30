<?php

namespace App\Services;

use App\Models\RevenueCatWebhookLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use Carbon\Carbon;

class RevenueCatWebhookService
{
    public function processWebhook(array $payload): void
    {
        $eventId = data_get($payload, 'event.id');

        if ($eventId) {
            $existingLog = RevenueCatWebhookLog::where('event_id', $eventId)->first();

            if ($existingLog && $existingLog->status === 'processed') {
                return;
            }

            if ($existingLog) {
                $existingLog->update([
                    'status' => 'pending',
                    'error_message' => null,
                ]);
                $log = $existingLog;
            } else {
                $log = RevenueCatWebhookLog::create([
                    'event_id' => $eventId,
                    'event_type' => data_get($payload, 'event.type', 'UNKNOWN'),
                    'app_user_id' => data_get($payload, 'event.app_user_id'),
                    'subscriber_id' => data_get($payload, 'event.subscriber_id'),
                    'product_id' => data_get($payload, 'event.product_id'),
                    'entitlement_id' => data_get($payload, 'event.entitlement_identifiers.0'),
                    'store' => data_get($payload, 'event.store'),
                    'original_transaction_id' => data_get($payload, 'event.original_transaction_id'),
                    'payload' => $payload,
                    'status' => 'pending',
                ]);
            }
        } else {
            $log = RevenueCatWebhookLog::create([
                'event_type' => data_get($payload, 'event.type', 'UNKNOWN'),
                'app_user_id' => data_get($payload, 'event.app_user_id'),
                'subscriber_id' => data_get($payload, 'event.subscriber_id'),
                'product_id' => data_get($payload, 'event.product_id'),
                'entitlement_id' => data_get($payload, 'event.entitlement_identifiers.0'),
                'store' => data_get($payload, 'event.store'),
                'original_transaction_id' => data_get($payload, 'event.original_transaction_id'),
                'payload' => $payload,
                'status' => 'pending',
            ]);
        }

        try {
            $eventType = data_get($payload, 'event.type');

            match ($eventType) {
                'INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE' => $this->handlePurchaseOrRenewal($payload),
                'CANCELLATION' => $this->handleCancellation($payload),
                'EXPIRATION' => $this->handleExpiration($payload),
                'BILLING_ISSUE' => $this->handleBillingIssue($payload),
                default => null,
            };

            $log->update([
                'status' => 'processed',
                'processed_at' => now(),
            ]);
        } catch (\Exception $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function handlePurchaseOrRenewal(array $payload): void
    {
        $event = $payload['event'] ?? [];
        $userId = data_get($event, 'app_user_id');
        $productId = data_get($event, 'product_id');
        $entitlementId = data_get($event, 'entitlement_identifiers.0');
        $originalTransactionId = data_get($event, 'original_transaction_id');
        $store = data_get($event, 'store');
        $expiresAtMs = data_get($event, 'expiration_at_ms');
        $expiresDate = $expiresAtMs ? Carbon::createFromTimestampMs($expiresAtMs) : null;
        $eventTimestampMs = data_get($event, 'event_timestamp_ms');
        $eventTime = $eventTimestampMs ? Carbon::createFromTimestampMs($eventTimestampMs) : now();
        $purchasedAtMs = data_get($event, 'purchased_at_ms');
        $purchasedAt = $purchasedAtMs ? Carbon::createFromTimestampMs($purchasedAtMs) : now();

        $user = User::find($userId);
        if (!$user) {
            throw new \Exception("User not found: {$userId}");
        }

        $plan = SubscriptionPlan::where('revenuecat_product_id', $productId)
            ->with('configs')
            ->first();

        if (!$plan) {
            throw new \Exception("Plan not found for product_id: {$productId}");
        }

        $currentActive = $user->subscriptions()
            ->active()
            ->orderByDesc('paid_at')
            ->orderByDesc('starts_at')
            ->first();

        if ($currentActive && $currentActive->revenuecat_original_transaction_id
            && $currentActive->revenuecat_original_transaction_id !== $originalTransactionId) {
            $currentTime = $currentActive->paid_at ?? $currentActive->starts_at ?? $currentActive->created_at;
            if ($currentTime && $eventTime->lt($currentTime)) {
                \Log::warning('RevenueCat: ignoring older purchase/renewal event', [
                    'app_user_id' => $userId,
                    'event_id' => data_get($event, 'id'),
                    'event_type' => data_get($event, 'type'),
                    'event_time' => $eventTime->toDateTimeString(),
                    'current_subscription_id' => $currentActive->id,
                    'current_subscription_time' => $currentTime->toDateTimeString(),
                ]);
                return;
            }
        }

        if ($originalTransactionId) {
            $existingByTransaction = UserSubscription::where('user_id', $user->id)
                ->where('revenuecat_original_transaction_id', $originalTransactionId)
                ->first();

            if ($existingByTransaction) {
                $existingByTransaction->update([
                    'status' => 'active',
                    'starts_at' => $purchasedAt,
                    'ends_at' => $expiresDate,
                    'renews_at' => $expiresDate,
                    'paid_at' => $purchasedAt,
                    'payment_method' => $store,
                ]);
                return;
            }
        }

        if ($currentActive) {
            $currentTime = $currentActive->paid_at ?? $currentActive->starts_at ?? $currentActive->created_at;
            $shouldCancel = !$currentTime || $eventTime->gte($currentTime);

            if ($shouldCancel) {
                $currentActive->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => now(),
                ]);
            }
        }

        UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'limits_snapshot' => $plan->getLimitsArray(),
            'features_snapshot' => $plan->getFeaturesArray(),
            'status' => 'active',
            'starts_at' => $purchasedAt,
            'ends_at' => $expiresDate,
            'renews_at' => $expiresDate,
            'is_paid' => true,
            'paid_at' => $purchasedAt,
            'payment_method' => $store,
            'revenuecat_subscriber_id' => data_get($event, 'subscriber_id'),
            'revenuecat_original_transaction_id' => $originalTransactionId,
            'revenuecat_product_id' => $productId,
            'revenuecat_entitlement_id' => $entitlementId,
            'revenuecat_store' => $store,
            'revenuecat_raw_data' => $payload,
        ]);
    }

    private function handleCancellation(array $payload): void
    {
        $event = $payload['event'] ?? [];
        $userId = data_get($event, 'app_user_id');
        $originalTransactionId = data_get($event, 'original_transaction_id');

        if (!$originalTransactionId) {
            \Log::warning('RevenueCat: cancellation without original_transaction_id', [
                'app_user_id' => $userId,
                'event_id' => data_get($event, 'id'),
                'event_type' => data_get($event, 'type'),
            ]);
            return;
        }

        $subscription = UserSubscription::where('user_id', $userId)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();

        if ($subscription) {
            if ($subscription->plan_slug === 'free') {
                return;
            }
            $subscription->update([
                'status' => 'canceled',
                'canceled_at' => now(),
            ]);
            return;
        }

        \Log::warning('RevenueCat: cancellation without matching subscription', [
            'app_user_id' => $userId,
            'original_transaction_id' => $originalTransactionId,
            'event_id' => data_get($event, 'id'),
        ]);
    }

    private function handleExpiration(array $payload): void
    {
        $event = $payload['event'] ?? [];
        $userId = data_get($event, 'app_user_id');
        $originalTransactionId = data_get($event, 'original_transaction_id');

        if (!$originalTransactionId) {
            \Log::warning('RevenueCat: expiration without original_transaction_id', [
                'app_user_id' => $userId,
                'event_id' => data_get($event, 'id'),
                'event_type' => data_get($event, 'type'),
            ]);
            return;
        }

        $subscription = UserSubscription::where('user_id', $userId)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();

        if ($subscription) {
            if ($subscription->plan_slug === 'free') {
                return;
            }
            $subscription->update([
                'status' => 'expired',
            ]);
            return;
        }

        \Log::warning('RevenueCat: expiration without matching subscription', [
            'app_user_id' => $userId,
            'original_transaction_id' => $originalTransactionId,
            'event_id' => data_get($event, 'id'),
        ]);
    }

    private function handleBillingIssue(array $payload): void
    {
        $event = $payload['event'] ?? [];
        $userId = data_get($event, 'app_user_id');

        \Log::warning("RevenueCat: Billing issue for user {$userId}", $payload);
    }
}
