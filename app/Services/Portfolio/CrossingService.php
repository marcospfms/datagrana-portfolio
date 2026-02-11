<?php

namespace App\Services\Portfolio;

use App\Helpers\PortfolioHelper;
use App\Models\Consolidated;
use App\Models\Portfolio;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Facades\DB;

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

        $yieldOnCostMonthlyByConsolidated = $this->buildYieldOnCostMonthlyMap(
            $consolidated->pluck('id')->all()
        );

        $crossing = PortfolioHelper::prepareCrossingData(
            $compositions,
            $consolidated,
            $compositionHistory,
            $portfolio,
            $yieldOnCostMonthlyByConsolidated,
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
            'yield_on_cost_monthly',
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

    private function buildYieldOnCostMonthlyMap(array $consolidatedIds): array
    {
        if (empty($consolidatedIds)) {
            return [];
        }

        $rankedEarnings = DB::table('earnings as e')
            ->selectRaw(
                'e.consolidated_id, e.net_value, e.quantity, ROW_NUMBER() OVER (PARTITION BY e.consolidated_id ORDER BY e.date DESC, e.id DESC) as rn'
            )
            ->whereIn('e.consolidated_id', $consolidatedIds);

        $earningsCalc = DB::query()
            ->fromSub($rankedEarnings, 'ranked_earnings')
            ->selectRaw('consolidated_id, SUM(net_value) as total_values, SUM(quantity) as total_quantities')
            ->where('rn', '<=', 12)
            ->groupBy('consolidated_id');

        return DB::table('consolidated as c')
            ->leftJoinSub($earningsCalc, 'earnings_calc', function ($join) {
                $join->on('c.id', '=', 'earnings_calc.consolidated_id');
            })
            ->whereIn('c.id', $consolidatedIds)
            ->selectRaw(
                'c.id as consolidated_id,
                CASE
                    WHEN COALESCE(earnings_calc.total_quantities, 0) > 0
                        AND c.average_purchase_price > 0
                    THEN ((COALESCE(earnings_calc.total_values, 0) / earnings_calc.total_quantities)
                          / c.average_purchase_price) * 100
                    ELSE 0
                END as yield_on_cost_monthly'
            )
            ->pluck('yield_on_cost_monthly', 'consolidated_id')
            ->map(fn ($value) => (float) $value)
            ->toArray();
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
