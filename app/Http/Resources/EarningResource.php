<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\AccountResource;
use App\Http\Resources\CompanyTickerResource;

class EarningResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consolidated_id' => $this->consolidated_id,
            'earning_type_id' => $this->earning_type_id,
            'company_earning_id' => $this->company_earning_id,
            'date' => $this->date?->toISOString(),
            'quantity' => $this->quantity,
            'net_value' => $this->net_value,
            'gross_value' => $this->gross_value,
            'tax' => $this->tax,
            'imported_with' => $this->imported_with,
            'earning_type' => new EarningTypeResource($this->whenLoaded('earningType')),
            'consolidated' => $this->whenLoaded('consolidated', function () {
                return [
                    'id' => $this->consolidated?->id,
                    'account_id' => $this->consolidated?->account_id,
                    'account' => $this->consolidated?->relationLoaded('account')
                        ? new AccountResource($this->consolidated->account)
                        : null,
                    'company_ticker_id' => $this->consolidated?->company_ticker_id,
                    'company_ticker' => $this->consolidated?->relationLoaded('companyTicker')
                        ? new CompanyTickerResource($this->consolidated->companyTicker)
                        : null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
