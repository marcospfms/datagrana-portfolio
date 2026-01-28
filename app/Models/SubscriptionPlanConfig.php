<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPlanConfig extends Model
{
    protected $table = 'subscription_plan_config';

    protected $fillable = [
        'subscription_plan_id',
        'name',
        'slug',
        'status',
        'config_key',
        'config_value',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'subscription_plan_id' => 'integer',
            'config_value' => 'integer',
            'is_enabled' => 'boolean',
            'status' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isLimit(): bool
    {
        return str_starts_with($this->config_key, 'max_');
    }

    public function isFeature(): bool
    {
        return str_starts_with($this->config_key, 'allow_');
    }
}
