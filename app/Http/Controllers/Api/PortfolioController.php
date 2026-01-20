<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StorePortfolioRequest;
use App\Http\Requests\Portfolio\UpdatePortfolioRequest;
use App\Http\Resources\PortfolioResource;
use App\Models\Portfolio;
use App\Services\Portfolio\CrossingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:80'],
        ]);

        $portfolios = $request->user()->portfolios()
            ->when($request->filled('name'), function ($query) use ($request) {
                $query->where('name', 'like', '%' . $request->name . '%');
            })
            ->withCount('compositions')
            ->orderBy('name')
            ->get();

        return $this->sendResponse(PortfolioResource::collection($portfolios));
    }

    public function store(StorePortfolioRequest $request): JsonResponse
    {
        $portfolio = $request->user()->portfolios()->create($request->validated());

        return $this->sendResponse(
            new PortfolioResource($portfolio),
            'Portfolio criado com sucesso.'
        );
    }

    public function show(Portfolio $portfolio): JsonResponse
    {
        $this->authorize('view', $portfolio);

        $portfolio->load([
            'compositions.treasure.treasureCategory',
            'compositions.companyTicker.company.companyCategory',
            'compositionHistories.treasure.treasureCategory',
            'compositionHistories.companyTicker.company.companyCategory',
        ]);

        $portfolio->setRelation(
            'compositions',
            $portfolio->compositions
                ->sortBy(fn ($composition) => $this->compositionSortKey($composition))
                ->values()
        );

        $portfolio->setRelation(
            'compositionHistories',
            $portfolio->compositionHistories
                ->sortBy(function ($composition) {
                    $deletedAt = $composition->deleted_at?->getTimestamp();
                    $order = $deletedAt ? -$deletedAt : 0;

                    return [$order, $this->compositionSortKey($composition)];
                })
                ->values()
        );

        return $this->sendResponse(new PortfolioResource($portfolio));
    }

    public function update(UpdatePortfolioRequest $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $portfolio->update($request->validated());

        return $this->sendResponse(
            new PortfolioResource($portfolio->fresh()),
            'Portfolio atualizado com sucesso.'
        );
    }

    public function destroy(Portfolio $portfolio): JsonResponse
    {
        $this->authorize('delete', $portfolio);

        $portfolio->delete();

        return $this->sendResponse([], 'Portfolio removido com sucesso.');
    }

    public function crossing(Portfolio $portfolio, Request $request, CrossingService $crossingService): JsonResponse
    {
        $this->authorize('view', $portfolio);

        $crossing = $crossingService->prepare($portfolio, $request->user());

        return $this->sendResponse([
            'portfolio' => new PortfolioResource($portfolio),
            'crossing' => $crossing,
        ]);
    }

    private function compositionSortKey($composition): string
    {
        if ($composition->treasure_id) {
            $categoryName = $composition->treasure?->treasureCategory?->name ?? '';
            $treasureCode = $composition->treasure?->code ?? '';

            return 'A_' . $categoryName . '_' . $treasureCode;
        }

        $reference = $composition->companyTicker?->company?->companyCategory?->reference ?? '';
        $isFii = in_array($reference, ['FII', 'ETF'], true);
        $categoryOrder = $isFii ? 'C_' : 'B_';
        $ticker = $composition->companyTicker?->code ?? '';
        $companyName = $composition->companyTicker?->company?->name ?? '';

        return $categoryOrder . $ticker . '_' . $companyName;
    }
}
