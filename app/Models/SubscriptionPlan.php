<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;
    protected $table = 'subscription_plans';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'is_active',
        'display_order',
        'revenuecat_product_id',
        'revenuecat_entitlement_id',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'decimal:2',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function configs(): HasMany
    {
        return $this->hasMany(SubscriptionPlanConfig::class, 'subscription_plan_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(UserSubscription::class, 'subscription_plan_id');
    }

    public function getLimit(string $configKey): ?int
    {
        $config = $this->configs()->where('config_key', $configKey)->first();
        return $config?->config_value;
    }

    public function isUnlimited(string $configKey): bool
    {
        return $this->getLimit($configKey) === null;
    }

    public function hasFeature(string $configKey): bool
    {
        $config = $this->configs()->where('config_key', $configKey)->first();
        return (bool) $config?->is_enabled;
    }

    public function getLimitsArray(): array
    {
        return $this->configs()
            ->where('config_key', 'LIKE', 'max_%')
            ->pluck('config_value', 'config_key')
            ->toArray();
    }

    public function getFeaturesArray(): array
    {
        return $this->configs()
            ->where('config_key', 'LIKE', 'allow_%')
            ->pluck('is_enabled', 'config_key')
            ->toArray();
    }

    public function getAllConfigsArray(): array
    {
        return [
            'limits' => $this->getLimitsArray(),
            'features' => $this->getFeaturesArray(),
        ];
    }

    public static function active(): \Illuminate\Support\Collection
    {
        return self::where('is_active', true)
            ->with('configs')
            ->orderBy('display_order')
            ->get();
    }
}
