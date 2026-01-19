<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'consolidated_id' => $this->consolidated_id,
            'date' => $this->date?->toISOString(),
            'operation' => $this->operation,
            'quantity' => (string) $this->quantity,
            'price' => (string) $this->price,
            'total_value' => (string) $this->total_value,
            'imported_with' => $this->imported_with,
            'consolidated' => new ConsolidatedResource($this->whenLoaded('consolidated')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
