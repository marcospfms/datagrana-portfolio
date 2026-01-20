<?php

namespace App\Services\Portfolio;

use App\Helpers\PortfolioHelper;
use App\Models\Consolidated;
use App\Models\Portfolio;
use App\Models\User;

class CrossingService
{
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

        return PortfolioHelper::prepareCrossingData($compositions, $consolidated, $compositionHistory, $portfolio);
    }
}
