<?php

namespace App\Http\Resources\Automation;

use Illuminate\Http\Resources\Json\JsonResource;

class AutomationEarningMatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->toDateString(),
            'quantity' => $this->quantity,
            'net_value' => $this->net_value,
            'gross_value' => $this->gross_value,
            'earning_type' => [
                'id' => $this->earning_type_id,
                'name' => $this->earningType?->name,
                'short_name' => $this->earningType?->short_name,
                'label' => $this->earningType?->label,
            ],
            'consolidated' => [
                'id' => $this->consolidated_id,
                'company_ticker' => [
                    'id' => $this->consolidated?->company_ticker_id,
                    'code' => $this->consolidated?->companyTicker?->code,
                ],
                'treasure' => [
                    'id' => $this->consolidated?->treasure_id,
                    'name' => $this->consolidated?->treasure?->name,
                    'code' => $this->consolidated?->treasure?->code,
                ],
            ],
        ];
    }
}
