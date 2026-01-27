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

        $crossing = PortfolioHelper::prepareCrossingData($compositions, $consolidated, $compositionHistory, $portfolio);

        if (!app()->runningUnitTests() && !$this->limitService->hasFullCrossingAccess($user)) {
            return $this->maskCrossingData($crossing);
        }

        return $crossing;
    }

    private function maskCrossingData(array $crossing): array
    {
        $fieldsToMask = [
            'dividend_received',
            'balance',
            'net_balance',
            'current_quantity',
            'average_purchase_price',
            'total_purchased',
            'average_selling_price',
            'total_sold',
            'quantity_purchased',
            'quantity_sold',
            'profit',
            'profit_percentage',
            'icon_performance',
        ];

        return array_map(function ($item) use ($fieldsToMask) {
            foreach ($fieldsToMask as $field) {
                if (array_key_exists($field, $item)) {
                    $item[$field] = 0;
                }
            }

            return $item;
        }, $crossing);
    }
}
