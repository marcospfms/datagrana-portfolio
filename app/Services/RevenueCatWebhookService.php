<?php

namespace App\Services;

use App\Models\RevenueCatWebhookLog;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserSubscriptionUsage;
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

            $subscription = match ($eventType) {
                'INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE' => $this->handlePurchaseOrRenewal($payload),
                'CANCELLATION' => $this->handleCancellation($payload),
                'EXPIRATION' => $this->handleExpiration($payload),
                'BILLING_ISSUE' => $this->handleBillingIssue($payload),
                default => null,
            };

            $log->update([
                'status' => 'processed',
                'processed_at' => now(),
                'user_subscription_id' => $subscription?->id,
            ]);
        } catch (\Exception $exception) {
            $log->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function handlePurchaseOrRenewal(array $payload): ?UserSubscription
    {
        $event = $payload['event'] ?? [];
        $eventType = data_get($event, 'type');
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

        $currentRank = $currentActive ? $this->getPlanRank($currentActive->plan_slug) : null;
        $incomingRank = $this->getPlanRank($plan->slug);
        $isDowngrade = $currentActive
            && $currentRank !== null
            && $incomingRank !== -1
            && $incomingRank < $currentRank;

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
                return null;
            }
        }

        if ($eventType === 'PRODUCT_CHANGE' && $isDowngrade && $currentActive) {
            $effectiveAt = $purchasedAt ?? $expiresDate ?? $currentActive->ends_at;
            if ($effectiveAt && $effectiveAt->isFuture()) {
                $currentActive->update([
                    'pending_plan_slug' => $plan->slug,
                    'pending_effective_at' => $effectiveAt,
                ]);
                return $currentActive->fresh();
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
                    'pending_plan_slug' => null,
                    'pending_effective_at' => null,
                ]);
                if ($eventType === 'RENEWAL') {
                    $existingByTransaction->increment('renewal_count');
                }
                $this->syncUsage($user, $existingByTransaction);
                return $existingByTransaction->fresh();
            }
        }

        if ($currentActive && $currentActive->plan_slug !== 'free') {
            $currentTime = $currentActive->paid_at ?? $currentActive->starts_at ?? $currentActive->created_at;
            $shouldCancel = !$currentTime || $eventTime->gte($currentTime);

            if ($shouldCancel) {
                $currentActive->update([
                    'status' => 'canceled',
                    'canceled_at' => now(),
                    'ends_at' => now(),
                    'pending_plan_slug' => null,
                    'pending_effective_at' => null,
                ]);
            }
        }

        $subscription = UserSubscription::create([
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
            'renewal_count' => $eventType === 'RENEWAL' ? 1 : 0,
            'pending_plan_slug' => null,
            'pending_effective_at' => null,
        ]);
        $this->syncUsage($user, $subscription);
        return $subscription;
    }

    private function handleCancellation(array $payload): ?UserSubscription
    {
        $event = $payload['event'] ?? [];
        $userId = data_get($event, 'app_user_id');
        $originalTransactionId = data_get($event, 'original_transaction_id');
        $periodType = strtoupper((string) data_get($event, 'period_type'));
        $isTrial = $periodType === 'TRIAL';

        if (!$originalTransactionId) {
            \Log::warning('RevenueCat: cancellation without original_transaction_id', [
                'app_user_id' => $userId,
                'event_id' => data_get($event, 'id'),
                'event_type' => data_get($event, 'type'),
            ]);
            return null;
        }

        $subscription = UserSubscription::where('user_id', $userId)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();

        if ($subscription) {
            if ($subscription->plan_slug !== 'free') {
                if ($isTrial) {
                    $subscription->update([
                        'status' => 'canceled',
                        'canceled_at' => now(),
                        'ends_at' => now(),
                        'pending_plan_slug' => null,
                        'pending_effective_at' => null,
                    ]);
                } else {
                    $subscription->update([
                        'canceled_at' => now(),
                        'pending_plan_slug' => null,
                        'pending_effective_at' => null,
                    ]);
                }
            }
            return $subscription;
        }

        \Log::warning('RevenueCat: cancellation without matching subscription', [
            'app_user_id' => $userId,
            'original_transaction_id' => $originalTransactionId,
            'event_id' => data_get($event, 'id'),
        ]);
        return null;
    }

    private function handleExpiration(array $payload): ?UserSubscription
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
            return null;
        }

        $subscription = UserSubscription::where('user_id', $userId)
            ->where('revenuecat_original_transaction_id', $originalTransactionId)
            ->first();

        if ($subscription) {
            if ($subscription->plan_slug !== 'free') {
                $subscription->update([
                    'status' => 'expired',
                    'pending_plan_slug' => null,
                    'pending_effective_at' => null,
                ]);
            }
            return $subscription;
        }

        \Log::warning('RevenueCat: expiration without matching subscription', [
            'app_user_id' => $userId,
            'original_transaction_id' => $originalTransactionId,
            'event_id' => data_get($event, 'id'),
        ]);
        return null;
    }

    private function handleBillingIssue(array $payload): ?UserSubscription
    {
        $event = $payload['event'] ?? [];
        $userId = data_get($event, 'app_user_id');

        \Log::warning("RevenueCat: Billing issue for user {$userId}", $payload);
        return null;
    }

    private function syncUsage(User $user, UserSubscription $subscription): void
    {
        $usage = UserSubscriptionUsage::where('user_id', $user->id)->first();

        if (!$usage) {
            UserSubscriptionUsage::create([
                'user_id' => $user->id,
                'user_subscription_id' => $subscription->id,
                'current_portfolios' => 0,
                'current_compositions' => 0,
                'current_positions' => 0,
                'current_accounts' => 0,
            ])->recalculate();
            return;
        }

        if ($usage->user_subscription_id !== $subscription->id) {
            $usage->user_subscription_id = $subscription->id;
            $usage->save();
        }

        if (!$usage->last_calculated_at) {
            $usage->recalculate();
        }
    }

    private function getPlanRank(?string $slug): int
    {
        $order = [
            'free' => 0,
            'starter' => 1,
            'pro' => 2,
            'premium' => 3,
        ];

        $normalized = $slug ? strtolower(trim($slug)) : '';
        return $order[$normalized] ?? -1;
    }
}
