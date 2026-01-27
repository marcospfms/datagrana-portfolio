<?php

namespace App\Helpers;

class PortfolioHelper
{
    public static function calculateToBuyQuantity(
        ?float $idealPercentage,
        ?float $targetValue,
        ?float $netBalance,
        ?float $lastPrice,
        ?string $deletedAt = null
    ): int|string|null {
        if ($deletedAt !== null) {
            return '-';
        }

        if ($lastPrice === null || $lastPrice <= 0) {
            return null;
        }

        if ($idealPercentage === null || $idealPercentage <= 0) {
            return 0;
        }

        $targetValue = $targetValue ?? 0;
        $netBalance = $netBalance ?? 0;

        $goal = ($idealPercentage * $targetValue) / 100;
        $toBuy = ($goal - $netBalance) / $lastPrice;

        if ($toBuy > 0) {
            return (int) floor($toBuy);
        }

        return 0;
    }

    public static function formatToBuyQuantity(int|string|null $toBuyQuantity): ?string
    {
        if ($toBuyQuantity === null) {
            return null;
        }

        if ($toBuyQuantity === '-') {
            return '-';
        }

        if ($toBuyQuantity > 0) {
            return number_format($toBuyQuantity, 0, ',', '.') . ' cotas';
        }

        return '0 cotas';
    }

    public static function prepareCrossingData($compositions, $consolidated, $compositionHistory, $portfolio): array
    {
        $crossingData = [];
        $historyKeys = [];
        $originalOrder = [];

        foreach ($compositionHistory as $history) {
            $historyKeys[self::getAssetKey($history)] = true;
        }

        foreach ($compositions as $composition) {
            $key = self::getAssetKey($composition);
            $originalOrder[] = $key;

            $lastPrice = null;
            if ($composition->treasure_id) {
                $lastPrice = null;
            } else {
                $lastPrice = $composition->companyTicker->last_price ?? null;
            }

                $crossingData[$key] = [
                    'ticker' => $composition->treasure_id
                        ? ($composition->treasure->code ?? 'N/A')
                        : ($composition->companyTicker->code ?? 'N/A'),
                    'name' => $composition->treasure_id
                        ? ($composition->treasure->name ?? 'N/A')
                        : ($composition->companyTicker->company->name ?? 'N/A'),
                    'category' => $composition->treasure_id
                        ? ($composition->treasure->treasureCategory->name ?? 'N/A')
                        : ($composition->companyTicker->company->companyCategory->name ?? 'N/A'),
                    'category_color' => $composition->treasure_id
                        ? ($composition->treasure->treasureCategory->color_hex ?? null)
                        : ($composition->companyTicker->company->companyCategory->color_hex ?? null),
                    'category_icon' => $composition->treasure_id
                        ? ($composition->treasure->treasureCategory->icon ?? null)
                        : ($composition->companyTicker->company->companyCategory->icon ?? null),
                    'reference' => $composition->treasure_id
                        ? ($composition->treasure->treasureCategory->reference ?? 'N/A')
                        : ($composition->companyTicker->company->companyCategory->reference ?? 'N/A'),
                'ideal_percentage' => $composition->percentage,
                'dividend_received' => 0,
                'balance' => 0,
                'net_balance' => 0,
                'current_quantity' => 0,
                'average_purchase_price' => 0,
                'total_purchased' => 0,
                'closed' => 0,
                'average_selling_price' => 0,
                'total_sold' => 0,
                'quantity_purchased' => 0,
                'quantity_sold' => 0,
                'profit' => 0,
                'profit_percentage' => 0,
                'icon_performance' => '',
                'last_price' => $lastPrice,
                'last_price_updated' => $composition->companyTicker?->last_price_updated?->toDateTimeString(),
                'to_buy_quantity' => null,
                'status' => 'not_positioned',
                'type' => $composition->treasure_id ? 'treasure' : 'company',
                'composition_id' => $composition->id,
                'consolidated_id' => null,
                'deleted_at' => $composition->deleted_at,
            ];
        }

        foreach ($consolidated as $consolidatedAsset) {
            $key = self::getAssetKey($consolidatedAsset);

            if (!in_array($key, $originalOrder, true)) {
                $originalOrder[] = $key;
            }

            $lastPrice = null;
            if ($consolidatedAsset->treasure_id) {
                $lastPrice = null;
            } else {
                $lastPrice = $consolidatedAsset->companyTicker->last_price ?? null;
            }

            if (isset($crossingData[$key])) {
                $crossingData[$key]['profit'] = $consolidatedAsset->profit ?? 0;
                $crossingData[$key]['profit_percentage'] = $consolidatedAsset->profit_percentage ?? 0;
                $crossingData[$key]['icon_performance'] = $consolidatedAsset->icon_performance ?? '';
                $crossingData[$key]['balance'] = $consolidatedAsset->balance ?? 0;
                $crossingData[$key]['dividend_received'] = $consolidatedAsset->dividend_received ?? 0;
                $crossingData[$key]['net_balance'] = $consolidatedAsset->net_balance ?? 0;
                $crossingData[$key]['current_quantity'] = $consolidatedAsset->quantity_current ?? 0;
                $crossingData[$key]['average_purchase_price'] = $consolidatedAsset->average_purchase_price ?? 0;
                $crossingData[$key]['total_purchased'] = $consolidatedAsset->total_purchased ?? 0;
                $crossingData[$key]['closed'] = $consolidatedAsset->closed ?? 0;
                $crossingData[$key]['average_selling_price'] = $consolidatedAsset->average_selling_price ?? 0;
                $crossingData[$key]['total_sold'] = $consolidatedAsset->total_sold ?? 0;
                $crossingData[$key]['quantity_purchased'] = $consolidatedAsset->quantity_purchased ?? 0;
                $crossingData[$key]['quantity_sold'] = $consolidatedAsset->quantity_sold ?? 0;
                $crossingData[$key]['last_price'] = $lastPrice;
                $crossingData[$key]['last_price_updated'] = $consolidatedAsset->companyTicker?->last_price_updated?->toDateTimeString()
                    ?? $crossingData[$key]['last_price_updated'];
                $toBuyQuantity = self::calculateToBuyQuantity(
                    $crossingData[$key]['ideal_percentage'],
                    $portfolio->target_value,
                    $consolidatedAsset->net_balance ?? 0,
                    $lastPrice,
                    $crossingData[$key]['deleted_at']
                );
                $crossingData[$key]['to_buy_quantity'] = $toBuyQuantity;
                $crossingData[$key]['to_buy_quantity_formatted'] = self::formatToBuyQuantity($toBuyQuantity);
                $crossingData[$key]['status'] = 'positioned';
                $crossingData[$key]['consolidated_id'] = $consolidatedAsset->id;
            } elseif (isset($historyKeys[$key])) {
                $history = null;
                foreach ($compositionHistory as $item) {
                    if (self::getAssetKey($item) === $key) {
                        $history = $item;
                        break;
                    }
                }

                $idealPercentage = $history ? $history->percentage : 0;
                $displayIdealPercentage = $idealPercentage;
                $toBuyQuantity = self::calculateToBuyQuantity(
                    $idealPercentage,
                    $portfolio->target_value,
                    $consolidatedAsset->net_balance ?? 0,
                    $lastPrice,
                    $history ? $history->deleted_at : null
                );
                $crossingData[$key] = [
                    'ticker' => $consolidatedAsset->treasure_id
                        ? ($consolidatedAsset->treasure->code ?? 'N/A')
                        : ($consolidatedAsset->companyTicker->code ?? 'N/A'),
                    'name' => $consolidatedAsset->treasure_id
                        ? ($consolidatedAsset->treasure->name ?? 'N/A')
                        : ($consolidatedAsset->companyTicker->company->name ?? 'N/A'),
                    'category' => $consolidatedAsset->treasure_id
                        ? ($consolidatedAsset->treasure->treasureCategory->name ?? 'N/A')
                        : ($consolidatedAsset->companyTicker->company->companyCategory->name ?? 'N/A'),
                    'category_color' => $consolidatedAsset->treasure_id
                        ? ($consolidatedAsset->treasure->treasureCategory->color_hex ?? null)
                        : ($consolidatedAsset->companyTicker->company->companyCategory->color_hex ?? null),
                    'category_icon' => $consolidatedAsset->treasure_id
                        ? ($consolidatedAsset->treasure->treasureCategory->icon ?? null)
                        : ($consolidatedAsset->companyTicker->company->companyCategory->icon ?? null),
                    'reference' => $consolidatedAsset->treasure_id
                        ? ($consolidatedAsset->treasure->treasureCategory->reference ?? 'N/A')
                        : ($consolidatedAsset->companyTicker->company->companyCategory->reference ?? 'N/A'),
                    'ideal_percentage' => $displayIdealPercentage,
                    'dividend_received' => $consolidatedAsset->dividend_received ?? 0,
                    'profit' => $consolidatedAsset->profit ?? 0,
                    'profit_percentage' => $consolidatedAsset->profit_percentage ?? 0,
                    'icon_performance' => $consolidatedAsset->icon_performance ?? '',
                    'balance' => $consolidatedAsset->balance ?? 0,
                    'net_balance' => $consolidatedAsset->net_balance ?? 0,
                    'current_quantity' => $consolidatedAsset->quantity_current ?? 0,
                    'average_purchase_price' => $consolidatedAsset->average_purchase_price ?? 0,
                    'total_purchased' => $consolidatedAsset->total_purchased ?? 0,
                    'closed' => $consolidatedAsset->closed ?? 0,
                    'average_selling_price' => $consolidatedAsset->average_selling_price ?? 0,
                    'total_sold' => $consolidatedAsset->total_sold ?? 0,
                    'quantity_purchased' => $consolidatedAsset->quantity_purchased ?? 0,
                    'quantity_sold' => $consolidatedAsset->quantity_sold ?? 0,
                    'last_price' => $lastPrice,
                    'last_price_updated' => $consolidatedAsset->companyTicker?->last_price_updated?->toDateTimeString(),
                    'to_buy_quantity' => $toBuyQuantity,
                    'to_buy_quantity_formatted' => self::formatToBuyQuantity($toBuyQuantity),
                    'status' => 'unwind_position',
                    'type' => $consolidatedAsset->treasure_id ? 'treasure' : 'company',
                    'composition_id' => null,
                    'consolidated_id' => $consolidatedAsset->id,
                    'was_in_portfolio' => true,
                    'deleted_at' => $history ? $history->deleted_at : null,
                ];
            }
        }

        $allItems = collect($crossingData)->sortBy(function ($item) {
            if ($item['type'] === 'treasure') {
                $treasureName = $item['name'] ?? '';
                $treasureCode = $item['code'] ?? '';
                return 'A_' . $treasureCode . '_' . $treasureName;
            }

            $reference = $item['reference'] ?? '';
            $isFii = in_array($reference, ['FII', 'ETF'], true);
            $categoryOrder = $isFii ? 'C_' : 'B_';
            $ticker = $item['ticker'] ?? '';
            $companyName = $item['name'] ?? '';
            return $categoryOrder . '_' . $ticker . '_' . $companyName;
        })->values();

        $ordered = [];
        foreach ($allItems as $item) {
            if ($item['status'] === 'not_positioned') {
                $toBuyQuantity = self::calculateToBuyQuantity(
                    $item['ideal_percentage'],
                    $portfolio->target_value,
                    0,
                    $item['last_price'],
                    $item['deleted_at']
                );
                $item['to_buy_quantity'] = $toBuyQuantity;
                $item['to_buy_quantity_formatted'] = self::formatToBuyQuantity($toBuyQuantity);
            }
            $ordered[] = $item;
        }

        return $ordered;
    }

    public static function getAssetKey($asset): string
    {
        if ($asset->treasure_id) {
            return 'treasure_' . $asset->treasure_id;
        }

        return 'company_' . $asset->company_ticker_id;
    }
}
