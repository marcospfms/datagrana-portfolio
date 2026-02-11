<?php

namespace App\Services\Consolidated;

use App\Models\Consolidated;
use App\Models\User;

class OverviewService
{
    public function buildForUser(User $user): array
    {
        $accountIds = $user->accounts()->pluck('id')->all();

        $openPositions = Consolidated::whereIn('account_id', $accountIds)
            ->open()
            ->with([
                'account.bank',
                'companyTicker.company.companyCategory',
                'treasure.treasureCategory',
            ])
            ->withSum('earnings as earnings_total', 'net_value')
            ->get();

        $closedPositions = Consolidated::whereIn('account_id', $accountIds)
            ->closed()
            ->with([
                'account.bank',
                'companyTicker.company.companyCategory',
                'treasure.treasureCategory',
            ])
            ->withSum('earnings as earnings_total', 'net_value')
            ->get();

        $totalCurrentValue = $openPositions->sum(fn (Consolidated $position) => $this->resolveCurrentValue($position));

        return [
            'active_positions_summary' => $this->buildActivePositionsSummary($openPositions),
            'capital_gains_summary' => $this->buildCapitalGainsSummary($closedPositions),
            'category_allocation' => $this->buildCategoryAllocation($openPositions, $totalCurrentValue),
            'institution_allocation' => $this->buildInstitutionAllocation($openPositions, $totalCurrentValue),
            'stocks_segment_allocation' => $this->buildStocksAllocation($openPositions, $totalCurrentValue, 'segment'),
            'stocks_sector_allocation' => $this->buildStocksAllocation($openPositions, $totalCurrentValue, 'sector'),
            'fiis_segment_allocation' => $this->buildFiisAllocation($openPositions, $totalCurrentValue, 'segment'),
            'fiis_sector_allocation' => $this->buildFiisAllocation($openPositions, $totalCurrentValue, 'sector'),
        ];
    }

    private function buildActivePositionsSummary($openPositions): array
    {
        $totalInvested = (float) $openPositions->sum('total_purchased');
        $totalCurrentValue = (float) $openPositions->sum(
            fn (Consolidated $position) => $this->resolveCurrentValue($position)
        );
        $totalDividends = (float) $openPositions->sum(
            fn (Consolidated $position) => (float) ($position->earnings_total ?? 0)
        );

        $capitalProfitLoss = $totalCurrentValue - $totalInvested;
        $capitalProfitPercentage = $totalInvested > 0 ? ($capitalProfitLoss / $totalInvested) * 100 : 0;
        $totalProfit = $capitalProfitLoss + $totalDividends;
        $totalProfitPercentage = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

        return [
            'total_invested' => round($totalInvested, 2),
            'total_current_value' => round($totalCurrentValue, 2),
            'total_dividends' => round($totalDividends, 2),
            'capital_profit_loss' => round($capitalProfitLoss, 2),
            'capital_profit_percentage' => round($capitalProfitPercentage, 2),
            'total_profit' => round($totalProfit, 2),
            'total_profit_percentage' => round($totalProfitPercentage, 2),
            'positions_count' => $openPositions->count(),
        ];
    }

    private function buildCapitalGainsSummary($closedPositions): array
    {
        $totalPurchased = (float) $closedPositions->sum('total_purchased');
        $totalSold = (float) $closedPositions->sum('total_sold');
        $totalDividends = (float) $closedPositions->sum(
            fn (Consolidated $position) => (float) ($position->earnings_total ?? 0)
        );

        $capitalGainOnly = $totalSold - $totalPurchased;
        $capitalGainPercentage = $totalPurchased > 0 ? ($capitalGainOnly / $totalPurchased) * 100 : 0;
        $totalCapitalGain = ($totalSold + $totalDividends) - $totalPurchased;
        $totalCapitalGainPercentage = $totalPurchased > 0
            ? ($totalCapitalGain / $totalPurchased) * 100
            : 0;

        return [
            'total_purchased' => round($totalPurchased, 2),
            'total_sold' => round($totalSold, 2),
            'total_dividends' => round($totalDividends, 2),
            'capital_gain_only' => round($capitalGainOnly, 2),
            'capital_gain_percentage' => round($capitalGainPercentage, 2),
            'total_capital_gain' => round($totalCapitalGain, 2),
            'total_capital_gain_percentage' => round($totalCapitalGainPercentage, 2),
            'positions_count' => $closedPositions->count(),
        ];
    }

    private function buildCategoryAllocation($openPositions, float $totalCurrentValue): array
    {
        $allocations = [];

        $companyCategoryGroups = $openPositions
            ->filter(fn (Consolidated $position) => $position->company_ticker_id !== null)
            ->groupBy(
                fn (Consolidated $position) => $position->companyTicker?->company?->companyCategory?->name ?? 'Sem Categoria'
            );

        foreach ($companyCategoryGroups as $categoryName => $positions) {
            $categoryValue = (float) $positions->sum(
                fn (Consolidated $position) => $this->resolveCurrentValue($position)
            );

            $allocations[] = [
                'name' => $categoryName,
                'value' => round($categoryValue, 2),
                'percentage' => $this->calculatePercentage($categoryValue, $totalCurrentValue),
                'type' => 'company',
            ];
        }

        $treasureCategoryGroups = $openPositions
            ->filter(fn (Consolidated $position) => $position->treasure_id !== null)
            ->groupBy(
                fn (Consolidated $position) => $position->treasure?->treasureCategory?->name ?? 'Sem Categoria'
            );

        foreach ($treasureCategoryGroups as $categoryName => $positions) {
            $categoryValue = (float) $positions->sum(
                fn (Consolidated $position) => $this->resolveCurrentValue($position)
            );

            $allocations[] = [
                'name' => $categoryName,
                'value' => round($categoryValue, 2),
                'percentage' => $this->calculatePercentage($categoryValue, $totalCurrentValue),
                'type' => 'treasure',
            ];
        }

        return $allocations;
    }

    private function buildInstitutionAllocation($openPositions, float $totalCurrentValue): array
    {
        return $openPositions
            ->groupBy(fn (Consolidated $position) => $position->account?->bank?->name ?? 'Instituição não identificada')
            ->map(function ($positions, $institutionName) use ($totalCurrentValue) {
                $institutionValue = (float) $positions->sum(
                    fn (Consolidated $position) => $this->resolveCurrentValue($position)
                );

                return [
                    'name' => $institutionName,
                    'value' => round($institutionValue, 2),
                    'percentage' => $this->calculatePercentage($institutionValue, $totalCurrentValue),
                    'positions_count' => $positions->count(),
                ];
            })
            ->values()
            ->all();
    }

    private function buildStocksAllocation($openPositions, float $totalCurrentValue, string $dimension): array
    {
        return $this->buildCompanyAllocationByDimension(
            $openPositions,
            $totalCurrentValue,
            $dimension,
            fn (Consolidated $position) => $this->isStockCategory(
                $position->companyTicker?->company?->companyCategory?->reference
            ),
            'Sem ' . ucfirst($dimension),
        );
    }

    private function buildFiisAllocation($openPositions, float $totalCurrentValue, string $dimension): array
    {
        return $this->buildCompanyAllocationByDimension(
            $openPositions,
            $totalCurrentValue,
            $dimension,
            fn (Consolidated $position) => $this->isFiiCategory(
                $position->companyTicker?->company?->companyCategory?->reference
            ),
            'Sem ' . ucfirst($dimension),
        );
    }

    private function buildCompanyAllocationByDimension(
        $openPositions,
        float $totalCurrentValue,
        string $dimension,
        callable $categoryFilter,
        string $fallbackName,
    ): array {
        $positions = $openPositions
            ->filter(fn (Consolidated $position) => $position->company_ticker_id !== null)
            ->filter($categoryFilter);

        if ($positions->isEmpty()) {
            return [];
        }

        return $positions
            ->groupBy(fn (Consolidated $position) => $position->companyTicker?->company?->{$dimension} ?: $fallbackName)
            ->map(function ($groupedPositions, $groupName) use ($totalCurrentValue) {
                $value = (float) $groupedPositions->sum(
                    fn (Consolidated $position) => $this->resolveCurrentValue($position)
                );

                if ($value <= 0) {
                    return null;
                }

                return [
                    'name' => $groupName,
                    'value' => round($value, 2),
                    'percentage' => $this->calculatePercentage($value, $totalCurrentValue),
                    'assets' => array_values(
                        array_unique(
                            $groupedPositions
                                ->map(fn (Consolidated $position) => $position->companyTicker?->code)
                                ->filter()
                                ->values()
                                ->all()
                        )
                    ),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    private function resolveCurrentValue(Consolidated $position): float
    {
        $quantity = (float) ($position->quantity_current ?? 0);
        $currentPrice = (float) ($position->average_purchase_price ?? 0);

        if ($position->company_ticker_id !== null) {
            $currentPrice = (float) ($position->companyTicker?->last_price ?? $position->average_purchase_price ?? 0);
        } elseif ($position->treasure_id !== null) {
            $currentPrice = (float) ($position->treasure?->last_unit_price ?? $position->average_purchase_price ?? 0);
        }

        return $quantity * $currentPrice;
    }

    private function calculatePercentage(float $value, float $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }

    private function isStockCategory(?string $reference): bool
    {
        if ($reference === null) {
            return false;
        }

        $normalized = mb_strtoupper(trim($reference));

        return in_array($normalized, ['ACAO', 'ACOES', 'AÇÕES'], true);
    }

    private function isFiiCategory(?string $reference): bool
    {
        if ($reference === null) {
            return false;
        }

        return mb_strtoupper(trim($reference)) === 'FII';
    }
}
