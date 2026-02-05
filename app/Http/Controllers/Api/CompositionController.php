<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Portfolio\StoreCompositionRequest;
use App\Http\Requests\Portfolio\UpdateCompositionBatchRequest;
use App\Http\Requests\Portfolio\UpdateCompositionRequest;
use App\Http\Resources\CompositionResource;
use App\Models\Composition;
use App\Models\CompositionHistory;
use App\Models\Portfolio;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompositionController extends BaseController
{
    public function store(StoreCompositionRequest $request, Portfolio $portfolio): JsonResponse
    {
        $this->authorize('update', $portfolio);

        $created = [];
        $validated = $request->validated();

        foreach ($validated['compositions'] as $composition) {
            $data = [
                'portfolio_id' => $portfolio->id,
                'percentage' => $composition['percentage'],
            ];

            if ($composition['type'] === 'treasure') {
                $data['treasure_id'] = $composition['asset_id'];
            } else {
                $data['company_ticker_id'] = $composition['asset_id'];
            }

            $created[] = $portfolio->compositions()->create($data);
        }

        $created = Composition::whereIn('id', collect($created)->pluck('id'))
            ->with([
                'treasure.treasureCategory',
                'companyTicker.company.companyCategory',
            ])
            ->get();

        return $this->sendResponse(
            CompositionResource::collection($created),
            'Composicoes adicionadas com sucesso.'
        );
    }

    public function update(
        UpdateCompositionRequest $request,
        Composition $composition,
        SubscriptionLimitService $limitService
    ): JsonResponse
    {
        $this->authorize('update', $composition->portfolio);
        $limitService->ensureCanEditComposition($request->user(), $composition);

        $composition->update([
            'percentage' => $request->percentage,
        ]);

        return $this->sendResponse(
            new CompositionResource(
                $composition->fresh()->load('treasure.treasureCategory', 'companyTicker.company.companyCategory')
            ),
            'Porcentagem atualizada com sucesso.'
        );
    }

    public function updateBatch(
        UpdateCompositionBatchRequest $request,
        SubscriptionLimitService $limitService
    ): JsonResponse
    {
        $updated = [];

        foreach ($request->validated()['compositions'] as $compositionData) {
            $composition = Composition::findOrFail($compositionData['id']);
            $this->authorize('update', $composition->portfolio);
            $limitService->ensureCanEditComposition($request->user(), $composition);

            $composition->update([
                'percentage' => $compositionData['percentage'],
            ]);

            $updated[] = $composition->fresh();
        }

        $updated = Composition::whereIn('id', collect($updated)->pluck('id'))
            ->with([
                'treasure.treasureCategory',
                'companyTicker.company.companyCategory',
            ])
            ->get();

        return $this->sendResponse(
            CompositionResource::collection($updated),
            'Composicoes atualizadas com sucesso.'
        );
    }

    public function destroy(
        Request $request,
        Composition $composition,
        SubscriptionLimitService $limitService
    ): JsonResponse
    {
        $this->authorize('update', $composition->portfolio);

        $validated = $request->validate([
            'save_to_history' => ['boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if ($request->boolean('save_to_history')) {
            $limitService->ensureCanViewCompositionHistory($request->user());
            $history = new CompositionHistory();
            $history->timestamps = false;
            $history->fill([
                'portfolio_id' => $composition->portfolio_id,
                'treasure_id' => $composition->treasure_id,
                'company_ticker_id' => $composition->company_ticker_id,
                'percentage' => $composition->percentage,
                'reason' => $validated['reason'] ?? null,
                'created_at' => $composition->created_at,
                'deleted_at' => now(),
            ]);
            $history->save();
        }

        $composition->delete();

        return $this->sendResponse([], 'Composicao removida com sucesso.');
    }
}
