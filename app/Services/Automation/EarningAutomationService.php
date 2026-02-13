<?php

namespace App\Services\Automation;

use App\Models\CompanyEarning;
use App\Models\CompanyTransaction;
use App\Models\Consolidated;
use App\Models\Earning;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class EarningAutomationService
{
    private array $accountIdsCache = [];

    public function getPendingCompanyEarnings(User $user, ?array $companyEarningIds = null): Collection
    {
        $query = $this->buildPendingCompanyEarningsQuery($user, $companyEarningIds);

        if (! $query) {
            return collect();
        }

        return $query->get();
    }

    public function hydrateEarningsStatus(
        User $user,
        Collection $pagamentos,
        bool $includePossible = true
    ): Collection {
        if ($pagamentos->isEmpty()) {
            return $pagamentos;
        }

        $accountIds = $this->getUserAccountIds($user);

        if (empty($accountIds)) {
            return collect();
        }

        $tickerIds = $pagamentos->pluck('company_ticker_id')->filter()->unique();
        $paymentDates = $pagamentos->pluck('payment_date')
            ->filter()
            ->map(function ($date) {
                $instance = $date instanceof Carbon ? $date : Carbon::parse($date);
                return $instance->toDateString();
            })
            ->unique()
            ->map(fn ($date) => Carbon::createFromFormat('Y-m-d', $date));

        if ($tickerIds->isEmpty() || $paymentDates->isEmpty()) {
            return $pagamentos;
        }

        $minDate = $paymentDates->min()->copy()->startOfDay();
        $maxDate = $paymentDates->max()->copy()->endOfDay();

        $existingEarnings = Earning::with([
            'earningType',
            'consolidated.companyTicker.company.category.coin',
            'consolidated.treasure',
        ])
            ->whereNull('company_earning_id')
            ->whereHas('consolidated', function ($query) use ($accountIds, $tickerIds) {
                $query->whereIn('account_id', $accountIds)
                    ->whereIn('company_ticker_id', $tickerIds);
            })
            ->whereBetween('date', [$minDate, $maxDate])
            ->get();

        $earningsGrouped = $existingEarnings->groupBy(function ($earning) {
            $tickerId = optional($earning->consolidated)->company_ticker_id;
            $date = optional($earning->date)->toDateString();
            return "{$tickerId}|{$date}";
        });

        return $pagamentos->map(function ($pagamento) use ($user, $earningsGrouped, $includePossible) {
            $key = "{$pagamento->company_ticker_id}|" . optional($pagamento->payment_date)->toDateString();
            $matches = $earningsGrouped->get($key, collect());

            $approvedDate = $pagamento->approved_date instanceof Carbon
                ? $pagamento->approved_date
                : Carbon::parse($pagamento->approved_date);

            $quantityUntilApproved = $this->getQuantityUntilApprovedDate(
                $user,
                $pagamento->company_ticker_id,
                $approvedDate
            );

            $pagamento->quantity_current_until_approved_date = $quantityUntilApproved;

            $calculatedValues = $pagamento->calculateValues($quantityUntilApproved);
            $pagamento->calculated_net_value = $calculatedValues['net_value'];
            $pagamento->calculated_gross_value = $calculatedValues['gross_value'];
            $pagamento->income_tax_rate = $calculatedValues['income_tax'];

            $exactMatch = $matches->first(function ($earning) use ($quantityUntilApproved, $calculatedValues) {
                $sameQuantity = $this->compareWithTolerance(
                    (float) $earning->quantity,
                    (float) $quantityUntilApproved,
                    0.0001
                );
                $sameNetValue = $this->compareWithTolerance(
                    (float) $earning->net_value,
                    (float) $calculatedValues['net_value'],
                    0.01
                );
                return $sameQuantity && $sameNetValue;
            });

            $pagamento->dividend_registered = $exactMatch;
            $pagamento->possible_dividend_registered = $includePossible ? $matches->values() : collect();

            return $pagamento;
        });
    }

    public function getHydratedPendingPayments(
        User $user,
        bool $includePossible = true,
        ?array $companyEarningIds = null
    ): Collection {
        $query = $this->buildPendingCompanyEarningsQuery($user, $companyEarningIds);

        if (! $query) {
            return collect();
        }

        $pagamentos = $query->get();

        if ($pagamentos->isEmpty()) {
            return $pagamentos;
        }

        return $this->hydrateEarningsStatus($user, $pagamentos, $includePossible);
    }

    public function paginateHydratedPendingPayments(
        User $user,
        int $perPage = 50,
        ?int $page = null,
        bool $includePossible = true
    ): LengthAwarePaginator {
        $query = $this->buildPendingCompanyEarningsQuery($user);

        if (! $query) {
            return new LengthAwarePaginator(collect(), 0, $perPage, $page ?? 1, [
                'path' => request()->url(),
                'pageName' => 'page',
            ]);
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);
        $hydratedItems = $this->hydrateEarningsStatus($user, $paginator->getCollection(), $includePossible);

        return $paginator->setCollection($hydratedItems);
    }

    public function summarizePayments(Collection $pagamentos): array
    {
        $countConsolidate = 0;
        $countNotRegistered = 0;
        $countDivergences = 0;

        $filtered = $pagamentos->filter(function ($pagamento) use (
            &$countConsolidate,
            &$countNotRegistered,
            &$countDivergences
        ) {
            if ($pagamento->quantity_current_until_approved_date <= 0) {
                return false;
            }

            if ($pagamento->dividend_registered && is_null($pagamento->dividend_registered->company_earning_id)) {
                $countConsolidate++;
                return true;
            }

            if ($pagamento->possible_dividend_registered instanceof Collection
                && $pagamento->possible_dividend_registered->isNotEmpty()
            ) {
                $countDivergences++;
                return true;
            }

            $countNotRegistered++;
            return true;
        })->values();

        return [
            'pagamentos' => $filtered,
            'countConsolidate' => $countConsolidate,
            'countNotRegistered' => $countNotRegistered,
            'countDivergences' => $countDivergences,
        ];
    }

    public function getQuantityForTicker(User $user, int $companyTickerId): float
    {
        $accountIds = $this->getUserAccountIds($user);

        if (empty($accountIds)) {
            return 0;
        }

        return (float) Consolidated::whereIn('account_id', $accountIds)
            ->where('company_ticker_id', $companyTickerId)
            ->sum('quantity_current');
    }

    public function findExactDividendFor(User $user, CompanyEarning $companyEarning): ?Earning
    {
        $pagamento = $this->getHydratedPendingPayments($user, false, [$companyEarning->id])->first();

        return $pagamento?->dividend_registered;
    }

    private function compareWithTolerance(float $value, float $target, float $tolerance): bool
    {
        return abs($value - $target) <= $tolerance;
    }

    private function getUserAccountIds(User $user): array
    {
        $userId = $user->getKey();

        if (! $userId) {
            return $user->accounts->pluck('id')->all();
        }

        if (! array_key_exists($userId, $this->accountIdsCache)) {
            $this->accountIdsCache[$userId] = $user->accounts->pluck('id')->all();
        }

        return $this->accountIdsCache[$userId];
    }

    private function buildPendingCompanyEarningsQuery(User $user, ?array $companyEarningIds = null): ?Builder
    {
        $accountIds = $this->getUserAccountIds($user);

        if (empty($accountIds)) {
            return null;
        }

        $positionsSubquery = Consolidated::query()
            ->selectRaw('company_ticker_id, MIN(created_at) as first_date_purchase')
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('company_ticker_id')
            ->where('quantity_current', '>', 0)
            ->groupBy('company_ticker_id');

        $query = CompanyEarning::query()
            ->with(['companyTicker.company.category.coin', 'earningType'])
            ->joinSub($positionsSubquery, 'user_positions', function ($join) {
                $join->on('user_positions.company_ticker_id', '=', 'company_earnings.company_ticker_id');
            })
            ->where('company_earnings.status', true)
            ->whereNotNull('company_earnings.payment_date')
            ->whereNotNull('company_earnings.approved_date')
            ->whereDate('company_earnings.payment_date', '<=', now())
            ->whereRaw('DATE(company_earnings.approved_date) >= DATE(user_positions.first_date_purchase)')
            ->whereDoesntHave('earnings', function ($query) use ($accountIds) {
                $query->whereHas('consolidated', function ($subQuery) use ($accountIds) {
                    $subQuery->whereIn('account_id', $accountIds);
                });
            })
            ->orderBy('company_earnings.payment_date', 'desc')
            ->select('company_earnings.*', 'user_positions.first_date_purchase');

        if (! empty($companyEarningIds)) {
            $query->whereIn('company_earnings.id', $companyEarningIds);
        }

        return $query;
    }

    public function pendingCompanyEarningsQuery(User $user, ?array $companyEarningIds = null): ?Builder
    {
        return $this->buildPendingCompanyEarningsQuery($user, $companyEarningIds);
    }

    public function getQuantityUntilApprovedDate(
        User $user,
        int $companyTickerId,
        Carbon $approvedDate
    ): float {
        $accountIds = $this->getUserAccountIds($user);

        if (empty($accountIds)) {
            return 0;
        }

        $transactions = CompanyTransaction::query()
            ->join('consolidated', 'company_transactions.consolidated_id', '=', 'consolidated.id')
            ->whereIn('consolidated.account_id', $accountIds)
            ->where('consolidated.company_ticker_id', $companyTickerId)
            ->whereDate('company_transactions.date', '<=', $approvedDate)
            ->select('company_transactions.operation', 'company_transactions.quantity')
            ->get();

        $totalQuantity = 0.0;

        foreach ($transactions as $transaction) {
            if ($transaction->operation === 'C') {
                $totalQuantity += (float) $transaction->quantity;
            } elseif ($transaction->operation === 'V') {
                $totalQuantity -= (float) $transaction->quantity;
            }
        }

        return max(0.0, $totalQuantity);
    }
}
