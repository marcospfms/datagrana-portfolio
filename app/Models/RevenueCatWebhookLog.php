<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RevenueCatWebhookLog extends Model
{
    protected $table = 'revenuecat_webhook_logs';

    protected $fillable = [
        'event_id',
        'event_type',
        'app_user_id',
        'subscriber_id',
        'product_id',
        'entitlement_id',
        'store',
        'original_transaction_id',
        'user_subscription_id',
        'payload',
        'status',
        'processed_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
