<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ConsolidatedResource;
use App\Models\Consolidated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsolidatedController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'closed' => ['nullable', 'boolean'],
        ]);

        $accountIds = $request->user()->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->when($request->account_id, fn ($query, $accountId) =>
                $query->where('account_id', $accountId)
            )
            ->when($request->has('closed'), fn ($query) =>
                $query->where('closed', $request->boolean('closed'))
            )
            ->with([
                'companyTicker.company.companyCategory',
                'treasure.treasureCategory',
                'account.bank',
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->sendResponse(ConsolidatedResource::collection($consolidated));
    }

    public function show(Consolidated $consolidated): JsonResponse
    {
        $this->authorize('view', $consolidated);

        return $this->sendResponse(
            new ConsolidatedResource(
                $consolidated->load(
                    'companyTicker.company.companyCategory',
                    'treasure.treasureCategory',
                    'account.bank'
                )
            )
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $accountIds = $request->user()->accounts()->pluck('id');

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->open()
            ->with([
                'companyTicker.company.companyCategory',
                'treasure.treasureCategory',
                'account.bank',
            ])
            ->get();

        $totalInvested = $consolidated->sum('total_purchased');
        $totalCurrent = $consolidated->sum('balance');
        $totalProfit = $totalCurrent - $totalInvested;
        $profitPercentage = $totalInvested > 0 ? ($totalProfit / $totalInvested) * 100 : 0;

        $byCategory = $consolidated->groupBy(function ($item) {
            return $item->companyTicker?->company?->companyCategory?->name
                ?? $item->treasure?->treasureCategory?->name
                ?? 'Sem categoria';
        })->map(function ($items, $categoryName) {
            $invested = $items->sum('total_purchased');
            $current = $items->sum('balance');
            $profit = $current - $invested;

            return [
                'category' => $categoryName,
                'count' => $items->count(),
                'invested' => round($invested, 2),
                'current' => round($current, 2),
                'profit' => round($profit, 2),
                'profit_percentage' => $invested > 0 ? round(($profit / $invested) * 100, 2) : 0,
            ];
        })->values();

        $byAccount = $consolidated->groupBy('account_id')->map(function ($items) {
            $account = $items->first()->account;
            $invested = $items->sum('total_purchased');
            $current = $items->sum('balance');
            $profit = $current - $invested;

            return [
                'account_id' => $account->id,
                'account_name' => $account->nickname ?? $account->account,
                'bank' => $account->bank?->nickname ?? $account->bank?->name,
                'count' => $items->count(),
                'invested' => round($invested, 2),
                'current' => round($current, 2),
                'profit' => round($profit, 2),
                'profit_percentage' => $invested > 0 ? round(($profit / $invested) * 100, 2) : 0,
            ];
        })->values();

        return $this->sendResponse([
            'total_invested' => round($totalInvested, 2),
            'total_current' => round($totalCurrent, 2),
            'total_profit' => round($totalProfit, 2),
            'profit_percentage' => round($profitPercentage, 2),
            'assets_count' => $consolidated->count(),
            'by_category' => $byCategory,
            'by_account' => $byAccount,
        ]);
    }
}
