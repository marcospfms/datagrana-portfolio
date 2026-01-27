<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class UserSubscription extends Model
{
    protected $table = 'user_subscriptions';

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'plan_name',
        'plan_slug',
        'price_monthly',
        'limits_snapshot',
        'features_snapshot',
        'status',
        'starts_at',
        'ends_at',
        'renews_at',
        'trial_ends_at',
        'canceled_at',
        'is_paid',
        'paid_at',
        'payment_method',
        'revenuecat_subscriber_id',
        'revenuecat_original_transaction_id',
        'revenuecat_product_id',
        'revenuecat_entitlement_id',
        'revenuecat_store',
        'revenuecat_raw_data',
        'cancellation_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'limits_snapshot' => 'array',
            'features_snapshot' => 'array',
            'is_paid' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'canceled_at' => 'datetime',
            'paid_at' => 'datetime',
            'revenuecat_raw_data' => 'json',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function usage(): HasOne
    {
        return $this->hasOne(UserSubscriptionUsage::class, 'user_subscription_id');
    }

    public function isActive(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->ends_at && Carbon::now()->isAfter($this->ends_at)) {
            return false;
        }

        return true;
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at
            && Carbon::now()->isBefore($this->trial_ends_at);
    }

    public function getLimit(string $limitKey): ?int
    {
        $limits = $this->limits_snapshot ?? [];
        return $limits[$limitKey] ?? null;
    }

    public function isUnlimited(string $limitKey): bool
    {
        return $this->getLimit($limitKey) === null;
    }

    public function hasReachedLimit(string $limitKey, int $currentUsage): bool
    {
        if ($this->isUnlimited($limitKey)) {
            return false;
        }

        return $currentUsage >= (int) $this->getLimit($limitKey);
    }

    public function hasFeature(string $featureKey): bool
    {
        $features = $this->features_snapshot ?? [];
        return (bool) ($features[$featureKey] ?? false);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', Carbon::now());
            });
    }
}
