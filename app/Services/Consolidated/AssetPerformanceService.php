<?php

namespace App\Services\Consolidated;

use App\Models\Consolidated;
use App\Models\User;
use Illuminate\Support\Collection;

class AssetPerformanceService
{
    /**
     * @param array{
     *   segment?: string|null,
     *   sort?: string|null,
     *   direction?: string|null,
     *   limit?: int|null
     * } $filters
     * @return array<string, mixed>
     */
    public function buildForUser(User $user, array $filters = []): array
    {
        $accountIds = $user->accounts()->pluck('id')->all();

        $positions = Consolidated::whereIn('account_id', $accountIds)
            ->open()
            ->whereNotNull('company_ticker_id')
            ->with([
                'companyTicker.company.companyCategory',
            ])
            ->get();

        $items = $this->buildItems($positions);

        if (!empty($filters['segment'])) {
            $segmentFilter = mb_strtolower((string) $filters['segment']);
            $items = $items
                ->filter(fn (array $item) => mb_strtolower((string) $item['segment']) === $segmentFilter)
                ->values();
        }

        $sort = (string) ($filters['sort'] ?? 'performance_pct');
        $direction = (string) ($filters['direction'] ?? 'desc');
        $items = $this->sortItems($items, $sort, $direction);

        if (!empty($filters['limit']) && (int) $filters['limit'] > 0) {
            $items = $items->take((int) $filters['limit'])->values();
        }

        return [
            'items' => $items->values()->all(),
            'segments' => $this->buildSegments($items),
            'summary' => $this->buildSummary($items),
            'best_asset' => $items->first(),
            'worst_asset' => $items->sortBy('performance_pct')->first(),
            'chart_data' => $items->map(fn (array $item) => [
                'ticker' => $item['ticker'],
                'segment' => $item['segment'],
                'value' => $item['performance_pct'],
            ])->values()->all(),
        ];
    }

    /**
     * @param Collection<int, Consolidated> $positions
     * @return Collection<int, array<string, mixed>>
     */
    private function buildItems(Collection $positions): Collection
    {
        return $positions
            ->groupBy(fn (Consolidated $position) => (string) ($position->companyTicker?->code ?? ''))
            ->filter(fn (Collection $group, string $ticker) => $ticker !== '')
            ->map(function (Collection $group, string $ticker): array {
                /** @var Consolidated $first */
                $first = $group->first();

                $quantityCurrent = (float) $group->sum(fn (Consolidated $position) => (float) ($position->quantity_current ?? 0));
                $invested = (float) $group->sum(fn (Consolidated $position) => (float) ($position->total_purchased ?? 0));
                $lastPrice = (float) ($first->companyTicker?->last_price ?? $first->average_purchase_price ?? 0);
                $currentValue = $quantityCurrent * $lastPrice;
                $performanceAbs = $currentValue - $invested;
                $performancePct = $invested > 0 ? ($performanceAbs / $invested) * 100 : 0.0;
                $averagePurchasePrice = $quantityCurrent > 0 ? ($invested / $quantityCurrent) : 0.0;
                $company = $first->companyTicker?->company;

                return [
                    'ticker' => $ticker,
                    'name' => $company?->nickname ?? $company?->name ?? $ticker,
                    'segment' => $company?->segment ?? 'Outros',
                    'sector' => $company?->sector,
                    'invested' => round($invested, 2),
                    'current_value' => round($currentValue, 2),
                    'performance_abs' => round($performanceAbs, 2),
                    'performance_pct' => round($performancePct, 2),
                    'quantity_current' => round($quantityCurrent, 8),
                    'average_purchase_price' => round($averagePurchasePrice, 8),
                    'last_price' => round($lastPrice, 8),
                    'category_reference' => $company?->companyCategory?->reference,
                    'category_color_hex' => $company?->companyCategory?->color_hex,
                ];
            })
            ->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function buildSegments(Collection $items): array
    {
        return $items
            ->groupBy(fn (array $item) => (string) ($item['segment'] ?? 'Outros'))
            ->map(fn (Collection $segmentItems, string $segment) => [
                'name' => $segment,
                'count' => $segmentItems->count(),
            ])
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $items): array
    {
        $invested = (float) $items->sum(fn (array $item) => (float) ($item['invested'] ?? 0));
        $currentValue = (float) $items->sum(fn (array $item) => (float) ($item['current_value'] ?? 0));
        $performanceAbs = $currentValue - $invested;
        $performancePct = $invested > 0 ? ($performanceAbs / $invested) * 100 : 0.0;

        return [
            'assets_count' => $items->count(),
            'positive_count' => $items->filter(fn (array $item) => (float) ($item['performance_pct'] ?? 0) > 0)->count(),
            'negative_count' => $items->filter(fn (array $item) => (float) ($item['performance_pct'] ?? 0) < 0)->count(),
            'neutral_count' => $items->filter(fn (array $item) => (float) ($item['performance_pct'] ?? 0) == 0.0)->count(),
            'total_invested' => round($invested, 2),
            'total_current_value' => round($currentValue, 2),
            'total_performance_abs' => round($performanceAbs, 2),
            'total_performance_pct' => round($performancePct, 2),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $items
     * @return Collection<int, array<string, mixed>>
     */
    private function sortItems(Collection $items, string $sort, string $direction): Collection
    {
        $allowedSort = [
            'ticker',
            'invested',
            'current_value',
            'performance_abs',
            'performance_pct',
        ];

        $sortField = in_array($sort, $allowedSort, true) ? $sort : 'performance_pct';
        $directionNormalized = mb_strtolower($direction) === 'asc' ? 'asc' : 'desc';

        $sorted = $directionNormalized === 'asc'
            ? $items->sortBy($sortField)
            : $items->sortByDesc($sortField);

        return $sorted->values();
    }
}
