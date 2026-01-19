<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CompanyCategoryResource;
use App\Http\Resources\CompanyTickerResource;
use App\Models\CompanyCategory;
use App\Models\CompanyTicker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends BaseController
{
    public function categories(): JsonResponse
    {
        $categories = CompanyCategory::active()
            ->orderBy('name')
            ->get();

        return $this->sendResponse(CompanyCategoryResource::collection($categories));
    }

    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['required', 'string', 'min:2', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:company_category,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $search = $request->string('search');
        $limit = $request->integer('limit', 20);

        $tickers = CompanyTicker::active()
            ->whereHas('company', fn ($query) => $query->active())
            ->where(function ($query) use ($search) {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('nickname', 'like', "%{$search}%");
                    });
            })
            ->when($request->category_id, function ($query, $categoryId) {
                $query->whereHas('company', fn ($companyQuery) =>
                    $companyQuery->where('company_category_id', $categoryId)
                );
            })
            ->with(['company.companyCategory'])
            ->orderByRaw('CASE WHEN code LIKE ? THEN 0 ELSE 1 END', ["{$search}%"])
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return $this->sendResponse(CompanyTickerResource::collection($tickers));
    }

    public function show(CompanyTicker $companyTicker): JsonResponse
    {
        return $this->sendResponse(
            new CompanyTickerResource(
                $companyTicker->load('company.companyCategory')
            )
        );
    }

    public function popular(Request $request): JsonResponse
    {
        $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:company_category,id'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $limit = $request->integer('limit', 10);

        $tickers = CompanyTicker::active()
            ->whereHas('company', fn ($query) => $query->active())
            ->when($request->category_id, function ($query, $categoryId) {
                $query->whereHas('company', fn ($companyQuery) =>
                    $companyQuery->where('company_category_id', $categoryId)
                );
            })
            ->with(['company.companyCategory'])
            ->orderBy('code')
            ->limit($limit)
            ->get();

        return $this->sendResponse(CompanyTickerResource::collection($tickers));
    }
}
