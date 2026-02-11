<?php

namespace App\Services\Portfolio;

use App\Helpers\PortfolioHelper;
use App\Models\Consolidated;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\SubscriptionLimitService;

class CrossingService
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function prepare(Portfolio $portfolio, User $user): array
    {
        $compositions = $portfolio->compositions()
            ->with([
                'treasure.treasureCategory',
                'companyTicker.company.companyCategory',
            ])
            ->get()
            ->sortBy(function ($composition) {
                if ($composition->treasure_id) {
                    return 'A_' . $composition->treasure->treasureCategory->name;
                }

                $reference = $composition->companyTicker->company->companyCategory->reference ?? '';
                $isFii = in_array($reference, ['FII', 'ETF'], true);
                $categoryOrder = $isFii ? 'C_' : 'B_';
                $ticker = $composition->companyTicker->code ?? '';
                $companyName = $composition->companyTicker->company->name ?? '';
                return $categoryOrder . '_' . $ticker . '_' . $companyName;
            });

        $accountIds = $user->accounts()->pluck('id')->toArray();
        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->open()
            ->with([
                'treasure.treasureCategory',
                'companyTicker.company.companyCategory',
            ])
            ->withSum('earnings as dividend_received', 'net_value')
            ->get()
            ->sortBy(function ($composition) {
                if ($composition->treasure_id) {
                    return 'A_' . $composition->treasure->treasureCategory->name;
                }

                $reference = $composition->companyTicker->company->companyCategory->reference ?? '';
                $isFii = in_array($reference, ['FII', 'ETF'], true);
                $categoryOrder = $isFii ? 'C_' : 'B_';
                $ticker = $composition->companyTicker->code ?? '';
                $companyName = $composition->companyTicker->company->name ?? '';
                return $categoryOrder . '_' . $ticker . '_' . $companyName;
            });

        $compositionHistory = $portfolio->compositionHistories()
            ->with([
                'treasure.treasureCategory',
                'companyTicker.company.companyCategory',
            ])
            ->get()
            ->sortBy(function ($composition) {
                $deletedAt = $composition->deleted_at ? -strtotime($composition->deleted_at) : 0;

                if ($composition->treasure_id) {
                    $customOrder = 'A_' . $composition->treasure->treasureCategory->name;
                } else {
                    $reference = $composition->companyTicker->company->companyCategory->reference ?? '';
                    $isFii = in_array($reference, ['FII', 'ETF'], true);
                    $categoryOrder = $isFii ? 'C_' : 'B_';
                    $customOrder = $categoryOrder . '_' . $composition->companyTicker?->code . '_' . $reference;
                }

                return [$deletedAt, $customOrder];
            });

        $yieldMetricsByConsolidated = $this->buildYieldMetricsMap($consolidated);

        $crossing = PortfolioHelper::prepareCrossingData(
            $compositions,
            $consolidated,
            $compositionHistory,
            $portfolio,
            $yieldMetricsByConsolidated,
        );
        $summary = $this->buildSummary($crossing, $portfolio);

        if (!$this->limitService->hasFullCrossingAccess($user)) {
            $crossing = $this->maskCrossingData($crossing);
            $summary = $this->maskCrossingSummary($summary);
        }

        return [
            'crossing' => $crossing,
            'summary' => $summary,
        ];
    }

    private function maskCrossingData(array $crossing): array
    {
        $fieldsToMask = [
            'current_quantity',
            'to_buy_quantity',
            'to_buy_quantity_formatted',
            'profit',
            'profit_percentage',
            'yield_monthly',
            'yield_on_cost_monthly',
            'yield_annual',
            'yield_on_cost_annual',
            'yoc_medal',
        ];

        return array_map(function ($item) use ($fieldsToMask) {
            foreach ($fieldsToMask as $field) {
                if (array_key_exists($field, $item)) {
                    $item[$field] = 'locked';
                }
            }

            if (array_key_exists('is_gold_yoc', $item)) {
                $item['is_gold_yoc'] = false;
            }

            return $item;
        }, $crossing);
    }

    private function buildYieldMetricsMap($consolidated): array
    {
        if ($consolidated->isEmpty()) {
            return [];
        }

        $result = [];

        foreach ($consolidated as $item) {
            if (!$item->company_ticker_id) {
                $result[$item->id] = [
                    'yield_monthly' => 0.0,
                    'yield_on_cost_monthly' => 0.0,
                    'yield_annual' => 0.0,
                    'yield_on_cost_annual' => 0.0,
                ];
                continue;
            }

            $averagePurchasePrice = (float) ($item->average_purchase_price ?? 0);
            if ($averagePurchasePrice <= 0) {
                $result[$item->id] = [
                    'yield_monthly' => 0.0,
                    'yield_on_cost_monthly' => 0.0,
                    'yield_annual' => 0.0,
                    'yield_on_cost_annual' => 0.0,
                ];
                continue;
            }

            $earnings = $item->earnings()
                ->orderByDesc('date')
                ->orderByDesc('id')
                ->take(12)
                ->get(['net_value', 'quantity']);

            if ($earnings->isEmpty()) {
                $result[$item->id] = [
                    'yield_monthly' => 0.0,
                    'yield_on_cost_monthly' => 0.0,
                    'yield_annual' => 0.0,
                    'yield_on_cost_annual' => 0.0,
                ];
                continue;
            }

            $totalValues = (float) $earnings->sum('net_value');
            $totalQuantities = (float) $earnings->sum('quantity');

            if ($totalQuantities <= 0) {
                $result[$item->id] = [
                    'yield_monthly' => 0.0,
                    'yield_on_cost_monthly' => 0.0,
                    'yield_annual' => 0.0,
                    'yield_on_cost_annual' => 0.0,
                ];
                continue;
            }

            $averagePerShare = $totalValues / $totalQuantities;
            $currentPrice = (float) ($item->companyTicker?->last_price ?? $averagePurchasePrice);
            $yieldMonthly = $currentPrice > 0 ? ($averagePerShare / $currentPrice) * 100 : 0.0;
            $yieldOnCostMonthly = ($averagePerShare / $averagePurchasePrice) * 100;

            $result[$item->id] = [
                'yield_monthly' => $yieldMonthly,
                'yield_on_cost_monthly' => $yieldOnCostMonthly,
                'yield_annual' => $yieldMonthly * 12,
                'yield_on_cost_annual' => $yieldOnCostMonthly * 12,
            ];
        }

        return $result;
    }

    private function buildSummary(array $crossing, Portfolio $portfolio): array
    {
        $positionedAssets = 0;
        $notPositionedAssets = 0;
        $unwindAssets = 0;
        $totalInvested = 0.0;
        $totalCurrentValue = 0.0;
        $totalProfit = 0.0;
        $totalToBuyQuantity = 0;
        $profitPercentages = [];
        $profitableAssets = 0;
        $lossAssets = 0;
        $perfectlyPositioned = 0;

        foreach ($crossing as $asset) {
            $status = $asset['status'] ?? null;
            if ($status === 'positioned') {
                $positionedAssets++;
            } elseif ($status === 'not_positioned') {
                $notPositionedAssets++;
            } elseif ($status === 'unwind_position') {
                $unwindAssets++;
            }

            $totalInvested += (float) ($asset['total_purchased'] ?? 0);
            $totalCurrentValue += (float) ($asset['balance'] ?? 0);
            $totalProfit += (float) ($asset['profit'] ?? 0);

            $qty = $asset['to_buy_quantity'] ?? null;
            if ($qty !== null && $qty !== '-') {
                $totalToBuyQuantity += (int) $qty;
            }

            $profitPercentage = (float) ($asset['profit_percentage'] ?? 0);
            if ($profitPercentage !== 0.0) {
                $profitPercentages[] = $profitPercentage;
            }
            if ($profitPercentage > 0) {
                $profitableAssets++;
            } elseif ($profitPercentage < 0) {
                $lossAssets++;
            }

            $ideal = (float) ($asset['ideal_percentage'] ?? 0);
            if ($ideal > 0) {
                $balance = (float) ($asset['balance'] ?? 0);
                $targetValue = (float) ($portfolio->target_value ?? 0);
                $meta = ($targetValue * $ideal) / 100;
                $progress = $meta > 0 ? ($balance / $meta) * 100 : 0;
                if ($progress >= 95 && $progress <= 105) {
                    $perfectlyPositioned++;
                }
            }
        }

        $avgProfitPercentage = count($profitPercentages) === 0
            ? 0
            : array_sum($profitPercentages) / count($profitPercentages);

        return [
            'totalInvested' => $totalInvested,
            'totalCurrentValue' => $totalCurrentValue,
            'resultValue' => $totalCurrentValue - $totalInvested,
            'positionedAssets' => $positionedAssets,
            'notPositionedAssets' => $notPositionedAssets,
            'unwindAssets' => $unwindAssets,
            'totalAssets' => count($crossing),
            'totalToBuyQuantity' => $totalToBuyQuantity,
            'avgProfitPercentage' => $avgProfitPercentage,
            'profitableAssets' => $profitableAssets,
            'lossAssets' => $lossAssets,
            'perfectlyPositioned' => $perfectlyPositioned,
            'totalProfit' => $totalProfit,
        ];
    }

    private function maskCrossingSummary(array $summary): array
    {
        $fieldsToMask = [
            'resultValue',
            'positionedAssets',
            'notPositionedAssets',
            'unwindAssets',
            'avgProfitPercentage',
            'profitableAssets',
            'lossAssets',
            'perfectlyPositioned',
            'totalProfit',
        ];

        foreach ($fieldsToMask as $field) {
            if (array_key_exists($field, $summary)) {
                $summary[$field] = 'locked';
            }
        }

        return $summary;
    }
}
