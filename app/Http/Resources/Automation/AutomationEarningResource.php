<?php

namespace App\Http\Resources\Automation;

use Illuminate\Http\Resources\Json\JsonResource;

class AutomationEarningResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'payment_date' => $this->payment_date?->toDateString(),
            'approved_date' => $this->approved_date?->toDateString(),
            'value' => $this->value,
            'company_ticker_id' => $this->company_ticker_id,
            'earning_type_id' => $this->earning_type_id,
            'company_ticker' => [
                'id' => $this->companyTicker?->id,
                'code' => $this->companyTicker?->code,
                'company' => [
                    'id' => $this->companyTicker?->company?->id,
                    'name' => $this->companyTicker?->company?->name,
                    'category' => [
                        'id' => $this->companyTicker?->company?->category?->id,
                        'name' => $this->companyTicker?->company?->category?->name,
                        'coin' => [
                            'id' => $this->companyTicker?->company?->category?->coin?->id,
                            'currency_symbol' => $this->companyTicker?->company?->category?->coin?->currency_symbol,
                        ],
                    ],
                ],
            ],
            'earning_type' => [
                'id' => $this->earningType?->id,
                'name' => $this->earningType?->name,
                'label' => $this->earningType?->label,
            ],
            'quantity_current_until_approved_date' => $this->quantity_current_until_approved_date ?? 0,
            'calculated_net_value' => $this->calculated_net_value ?? null,
            'calculated_gross_value' => $this->calculated_gross_value ?? null,
            'income_tax_rate' => $this->income_tax_rate ?? null,
            'dividend_registered' => $this->when(
                $this->dividend_registered,
                fn () => new AutomationEarningMatchResource($this->dividend_registered)
            ),
            'possible_dividend_registered' => $this->when(
                $this->possible_dividend_registered,
                fn () => AutomationEarningMatchResource::collection($this->possible_dividend_registered)
            ),
        ];
    }
}
