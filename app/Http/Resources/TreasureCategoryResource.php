<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TreasureCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'coin_id' => $this->coin_id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'reference' => $this->reference,
            'can_set_net_balance' => $this->can_set_net_balance,
            'color_hex' => $this->color_hex,
            'icon' => $this->icon,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
