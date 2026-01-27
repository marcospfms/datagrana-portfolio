<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_monthly' => (string) $this->price_monthly,
            'limits' => $this->getLimitsArray(),
            'features' => $this->getFeaturesArray(),
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
            'revenuecat_product_id' => $this->revenuecat_product_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
