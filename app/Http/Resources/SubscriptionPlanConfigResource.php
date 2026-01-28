<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanConfigResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'status' => $this->status,
            'config_key' => $this->config_key,
            'config_value' => $this->config_value,
            'is_enabled' => $this->is_enabled,
        ];
    }
}
