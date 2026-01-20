<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompositionHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->treasure_id ? 'treasure' : 'company';

        return [
            'id' => $this->id,
            'portfolio_id' => $this->portfolio_id,
            'treasure_id' => $this->treasure_id,
            'company_ticker_id' => $this->company_ticker_id,
            'type' => $type,
            'percentage' => $this->percentage !== null ? (string) $this->percentage : null,
            'reason' => $this->reason,
            'treasure' => new TreasureResource($this->whenLoaded('treasure')),
            'company_ticker' => new CompanyTickerResource($this->whenLoaded('companyTicker')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
        ];
    }
}
