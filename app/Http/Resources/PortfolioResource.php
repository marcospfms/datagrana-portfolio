<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PortfolioResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $byCategory = collect($this->total_percentage_by_category ?? [])
            ->map(function ($item) {
                $type = $item->type ?? $item['type'] ?? null;
                $category = $item->category ?? $item['category'] ?? null;
                $sum = $item->sum ?? $item['sum'] ?? 0;
                $label = $item->label ?? $item['label'] ?? null;

                $categoryResource = null;
                if ($category) {
                    $categoryResource = $type === 'treasure'
                        ? new TreasureCategoryResource($category)
                        : new CompanyCategoryResource($category);
                }

                return [
                    'label' => $label,
                    'type' => $type,
                    'sum' => (string) $sum,
                    'category' => $categoryResource,
                ];
            })
            ->values();

        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'name' => $this->name,
            'month_value' => (string) $this->month_value,
            'target_value' => (string) $this->target_value,
            'total_percentage' => (string) $this->total_percentage,
            'total_percentage_by_category' => $byCategory,
            'compositions_count' => $this->compositions_count ?? $this->compositions()->count(),
            'compositions' => CompositionResource::collection($this->whenLoaded('compositions')),
            'composition_histories' => CompositionHistoryResource::collection($this->whenLoaded('compositionHistories')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
