<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'cnpj' => $this->cnpj,
            'photo' => $this->photo === 'https://icons.brapi.dev/icons/BRAPI.svg' ? null : $this->photo,
            'segment' => $this->segment,
            'sector' => $this->sector,
            'subsector' => $this->subsector,
            'status' => $this->status,
            'category' => new CompanyCategoryResource($this->whenLoaded('companyCategory')),
            'tickers' => CompanyTickerResource::collection($this->whenLoaded('tickers')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
