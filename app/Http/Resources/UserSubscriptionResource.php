<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserSubscriptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'plan' => [
                'name' => $this->plan_name,
                'slug' => $this->plan_slug,
                'price_monthly' => (string) $this->price_monthly,
            ],
            'limits' => $this->limits_snapshot ?? [],
            'features' => $this->features_snapshot ?? [],
            'usage' => $this->whenLoaded('usage', function () {
                $compositionsByPortfolio = $this->usage->getCompositionsByPortfolio();
                return [
                    'current_portfolios' => $this->usage->current_portfolios,
                    'current_compositions' => $this->usage->current_compositions,
                    'current_compositions_per_portfolio' =>
                        $this->usage->getMaxCompositionsPerPortfolio(),
                    'compositions_by_portfolio' => $compositionsByPortfolio,
                    'current_positions' => $this->usage->current_positions,
                    'current_accounts' => $this->usage->current_accounts,
                    'last_calculated_at' => $this->usage->last_calculated_at?->toIso8601String(),
                ];
            }),
            'status' => $this->status,
            'is_active' => $this->isActive(),
            'is_trialing' => $this->isTrialing(),
            'has_had_paid_plan' => (bool) ($this->has_had_paid_plan ?? false),
            'pending_plan_slug' => $this->pending_plan_slug,
            'pending_effective_at' => $this->pending_effective_at?->toIso8601String(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'renews_at' => $this->renews_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
