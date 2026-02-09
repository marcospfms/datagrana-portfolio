<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyTickerQuickResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'company' => $this->whenLoaded('company', function () {
                $category = $this->company?->companyCategory;

                return [
                    'id' => $this->company?->id,
                    'name' => $this->company?->name,
                    'photo' => $this->company?->photo === 'https://icons.brapi.dev/icons/BRAPI.svg'
                        ? null
                        : $this->company?->photo,
                    'category' => $category
                        ? [
                            'id' => $category->id,
                            'name' => $category->name,
                            'short_name' => $category->short_name,
                            'color_hex' => $category->color_hex,
                            'icon' => $category->icon,
                        ]
                        : null,
                ];
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
