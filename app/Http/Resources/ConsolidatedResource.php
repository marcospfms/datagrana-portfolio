<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConsolidatedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $allowedIds = $request->attributes->get('allowed_position_ids');
        $isLocked = false;

        if (!$this->closed && is_array($allowedIds)) {
            $isLocked = !in_array($this->id, $allowedIds, true);
        }

        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'treasure_id' => $this->treasure_id,
            'company_ticker_id' => $this->company_ticker_id,
            'average_purchase_price' => (string) $this->average_purchase_price,
            'quantity_current' => (string) $this->quantity_current,
            'total_purchased' => (string) $this->total_purchased,
            'closed' => $this->closed,
            'average_selling_price' => $this->average_selling_price !== null ? (string) $this->average_selling_price : null,
            'total_sold' => $this->total_sold !== null ? (string) $this->total_sold : null,
            'quantity_purchased' => $this->quantity_purchased !== null ? (string) $this->quantity_purchased : null,
            'quantity_sold' => $this->quantity_sold !== null ? (string) $this->quantity_sold : null,
            'balance' => (string) $this->balance,
            'net_balance' => (string) $this->net_balance,
            'profit' => (string) $this->profit,
            'profit_percentage' => (string) $this->profit_percentage,
            'is_locked' => $isLocked,
            'account' => new AccountResource($this->whenLoaded('account')),
            'company_ticker' => new CompanyTickerResource($this->whenLoaded('companyTicker')),
            'company_transactions' => CompanyTransactionResource::collection(
                $this->whenLoaded('companyTransactions')
            ),
            'treasure_transactions' => TreasureTransactionResource::collection(
                $this->whenLoaded('treasureTransactions')
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
