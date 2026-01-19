<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTickerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'code' => $this->code,
            'trade_code' => $this->trade_code,
            'status' => $this->status,
            'can_update' => $this->can_update,
            'last_price' => $this->last_price !== null ? (string) $this->last_price : null,
            'last_price_updated' => $this->last_price_updated?->toISOString(),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
