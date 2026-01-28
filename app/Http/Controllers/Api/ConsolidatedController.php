<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ConsolidatedResource;
use App\Models\Consolidated;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsolidatedController extends BaseController
{
    public function index(Request $request, SubscriptionLimitService $limitService): JsonResponse
    {
        $request->validate([
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'closed' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'min:1', 'max:100'],
        ]);

        $accountIds = $request->user()->accounts()->pluck('id');
        $perPage = 10;
        $subscription = $limitService->ensureUserHasSubscription($request->user());
        $maxPositions = $subscription->getLimit('max_positions');
        $allowedIds = null;

        if ($maxPositions !== null) {
            $allowedIds = Consolidated::whereIn('account_id', $accountIds)
                ->open()
                ->orderBy('created_at')
                ->limit($maxPositions)
                ->pluck('id')
                ->all();
        }

        $request->attributes->set('allowed_position_ids', $allowedIds);

        $consolidated = Consolidated::whereIn('account_id', $accountIds)
            ->when($request->account_id, fn ($query, $accountId) =>
                $query->where('account_id', $accountId)
            )
            ->when($request->has('closed'), fn ($query) =>
                $query->where('closed', $request->boolean('closed'))
            )
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($filterQuery) use ($search) {
                    $filterQuery->whereHas('companyTicker.company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('nickname', 'like', "%{$search}%");
                    })
                        ->orWhereHas('companyTicker', function ($tickerQuery) use ($search) {
                            $tickerQuery->where('code', 'like', "%{$search}%");
                        })
                        ->orWhereHas('treasure', function ($treasureQuery) use ($search) {
                            $treasureQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
            ->with([
                'companyTicker.company.companyCategory',
                'treasure.treasureCategory',
                'account.bank',
            ])
            ->orderBy('closed', 'asc')
            ->orderByRaw('CASE WHEN treasure_id IS NOT NULL THEN (SELECT code FROM treasures WHERE id = treasure_id) END ASC')
            ->orderByRaw('CASE WHEN company_ticker_id IS NOT NULL THEN (SELECT code FROM company_tickers WHERE id = company_ticker_id) END ASC')
            ->paginate($perPage)
            ->withQueryString();

        return ConsolidatedResource::collection($consolidated)->response();
    }

    public function show(Consolidated $consolidated): JsonResponse
    {
        $this->authorize('view', $consolidated);

        $consolidated->load([
            'companyTicker.company.companyCategory',
            'treasure.treasureCategory',
            'account.bank',
        ]);

        $companyTransactions = $consolidated->companyTransactions()
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($transaction) => [
                'id' => $transaction->id,
                'type' => 'company',
                'date' => $transaction->date?->toISOString(),
                'operation' => $transaction->operation,
                'quantity' => (string) $transaction->quantity,
                'price' => (string) $transaction->price,
                'total_value' => (string) $transaction->total_value,
                'created_at' => $transaction->created_at?->toISOString(),
                'updated_at' => $transaction->updated_at?->toISOString(),
            ]);

        $treasureTransactions = $consolidated->treasureTransactions()
            ->orderBy('date', 'desc')
            ->get()
            ->map(fn ($transaction) => [
                'id' => $transaction->id,
                'type' => 'treasure',
                'date' => $transaction->date?->toISOString(),
                'operation' => $transaction->operation,
                'quantity' => (string) $transaction->quantity,
                'price' => $transaction->price !== null ? (string) $transaction->price : null,
                'invested_value' => (string) $transaction->invested_value,
                'created_at' => $transaction->created_at?->toISOString(),
                'updated_at' => $transaction->updated_at?->toISOString(),
            ]);

        $transactions = $companyTransactions
            ->concat($treasureTransactions)
            ->sortByDesc('date')
            ->values();

        $perPage = 10;
        $page = (int) request()->query('page', 1);
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $transactions->forPage($page, $perPage)->values(),
            $transactions->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $payload = (new ConsolidatedResource($consolidated))->resolve();
        $payload['transactions'] = $paginated;

        return $this->sendResponse($payload);
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
            $first = $items->first();
            $category = $first?->companyTicker?->company?->companyCategory
                ?? $first?->treasure?->treasureCategory;
            $categoryColor = $category?->color_hex;
            $categoryIcon = $category?->icon;

            return [
                'category' => $categoryName,
                'color_hex' => $categoryColor,
                'icon' => $categoryIcon,
                'count' => $items->count(),
                'invested' => round($invested, 2),
                'current' => round($current, 2),
                'profit' => round($profit, 2),
                'profit_percentage' => $invested > 0 ? round(($profit / $invested) * 100, 2) : 0,
            ];
        })->values();

        $byAccount = $consolidated->groupBy('account_id')->map(function ($items) {
            $account = $items->first()->account;
            $bank = $account->bank;
            $invested = $items->sum('total_purchased');
            $current = $items->sum('balance');
            $profit = $current - $invested;

            return [
                'account_id' => $account->id,
                'account_name' => $account->nickname ?? $account->account,
                'bank' => $bank?->nickname ?? $bank?->name,
                'bank_photo' => $bank?->photo ? trim($bank->photo) : null,
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
