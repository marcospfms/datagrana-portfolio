<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreasureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'treasure_category_id' => $this->treasure_category_id,
            'name' => $this->name,
            'code' => $this->code,
            'status' => $this->status,
            'is_overdue' => $this->is_overdue,
            'can_buy' => $this->can_buy,
            'can_sell' => $this->can_sell,
            'last_unit_price' => $this->last_unit_price !== null ? (string) $this->last_unit_price : null,
            'last_unit_price_updated' => $this->last_unit_price_updated?->toISOString(),
            'treasure_category' => new TreasureCategoryResource($this->whenLoaded('treasureCategory')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
