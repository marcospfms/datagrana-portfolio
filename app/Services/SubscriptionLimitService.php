<?php

namespace App\Services;

use App\Exceptions\SubscriptionLimitExceededException;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserSubscriptionUsage;
use App\Models\Account;
use App\Models\Composition;
use App\Models\Consolidated;
use App\Models\Portfolio;
use Illuminate\Database\Eloquent\Builder;

class SubscriptionLimitService
{
    public function canCreatePortfolio(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        $usage = $this->getOrCreateUsage($user, $subscription);

        if ($subscription->isUnlimited('max_portfolios')) {
            return true;
        }

        return $usage->current_portfolios < $subscription->getLimit('max_portfolios');
    }

    public function canAddComposition(User $user, ?Portfolio $portfolio = null, int $pending = 1): bool
    {
        $subscription = $this->getActiveSubscription($user);

        if ($subscription->isUnlimited('max_compositions')) {
            return true;
        }

        $limit = $subscription->getLimit('max_compositions');

        if (!$portfolio) {
            $usage = $this->getOrCreateUsage($user, $subscription);
            return $usage->current_compositions + $pending <= $limit;
        }

        $currentCount = $portfolio->compositions()->count();

        return $currentCount + $pending <= $limit;
    }

    public function canCreatePosition(User $user, int $pending = 1): bool
    {
        $subscription = $this->getActiveSubscription($user);
        $usage = $this->getOrCreateUsage($user, $subscription);

        if ($subscription->isUnlimited('max_positions')) {
            return true;
        }

        return $usage->current_positions + $pending <= $subscription->getLimit('max_positions');
    }

    public function canCreateAccount(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        $usage = $this->getOrCreateUsage($user, $subscription);

        if ($subscription->isUnlimited('max_accounts')) {
            return true;
        }

        return $usage->current_accounts < $subscription->getLimit('max_accounts');
    }

    public function canEditAccount(User $user, Account $account): bool
    {
        if ($account->user_id !== $user->id) {
            return false;
        }

        $subscription = $this->getActiveSubscription($user);

        if ($subscription->isUnlimited('max_accounts')) {
            return true;
        }

        $allowedIds = $this->getAllowedIds(
            Account::where('user_id', $user->id),
            $subscription->getLimit('max_accounts')
        );

        return in_array($account->id, $allowedIds, true);
    }

    public function canEditPortfolio(User $user, Portfolio $portfolio): bool
    {
        if ($portfolio->user_id !== $user->id) {
            return false;
        }

        $subscription = $this->getActiveSubscription($user);

        if ($subscription->isUnlimited('max_portfolios')) {
            return true;
        }

        $allowedIds = $this->getAllowedIds(
            Portfolio::where('user_id', $user->id),
            $subscription->getLimit('max_portfolios')
        );

        return in_array($portfolio->id, $allowedIds, true);
    }

    public function canEditComposition(User $user, Composition $composition): bool
    {
        $portfolio = $composition->portfolio;

        if (!$portfolio || $portfolio->user_id !== $user->id) {
            return false;
        }

        $subscription = $this->getActiveSubscription($user);

        if ($subscription->isUnlimited('max_compositions')) {
            return true;
        }

        $allowedIds = $this->getAllowedIds(
            Composition::where('portfolio_id', $portfolio->id),
            $subscription->getLimit('max_compositions')
        );

        return in_array($composition->id, $allowedIds, true);
    }

    public function canEditPosition(User $user, Consolidated $consolidated): bool
    {
        if ($consolidated->closed) {
            return true;
        }

        $subscription = $this->getActiveSubscription($user);

        if ($subscription->isUnlimited('max_positions')) {
            return true;
        }

        $allowedIds = $this->getAllowedIds(
            Consolidated::forUser($user)->open(),
            $subscription->getLimit('max_positions')
        );

        return in_array($consolidated->id, $allowedIds, true);
    }

    public function hasFullCrossingAccess(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        return $subscription->hasFeature('allow_full_crossing');
    }

    public function canViewCompositionHistory(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        return $subscription->hasFeature('allow_composition_history');
    }

    public function canViewCategoryAnalysis(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        return $subscription->hasFeature('allow_category_analysis');
    }

    public function canViewMultiPortfolioAnalysis(User $user): bool
    {
        $subscription = $this->getActiveSubscription($user);
        return $subscription->hasFeature('allow_multi_portfolio_analysis');
    }

    public function ensureCanCreatePortfolio(User $user): void
    {
        if (!$this->canCreatePortfolio($user)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_portfolios');
            throw new SubscriptionLimitExceededException(
                "Voce atingiu o limite de {$limit} carteiras do plano {$subscription->plan_name}. Faca upgrade para criar mais."
            );
        }
    }

    public function ensureCanAddComposition(User $user, ?Portfolio $portfolio = null, int $pending = 1): void
    {
        if (!$this->canAddComposition($user, $portfolio, $pending)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_compositions');
            throw new SubscriptionLimitExceededException(
                "Voce atingiu o limite de {$limit} composicoes por carteira no plano {$subscription->plan_name}. Faca upgrade para adicionar mais."
            );
        }
    }

    public function ensureCanCreatePosition(User $user, int $pending = 1): void
    {
        if (!$this->canCreatePosition($user, $pending)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_positions');
            throw new SubscriptionLimitExceededException(
                "Voce atingiu o limite de {$limit} posicoes ativas do plano {$subscription->plan_name}. Faca upgrade para criar mais."
            );
        }
    }

    public function ensureCanCreateAccount(User $user): void
    {
        if (!$this->canCreateAccount($user)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_accounts');
            throw new SubscriptionLimitExceededException(
                "Voce atingiu o limite de {$limit} contas do plano {$subscription->plan_name}. Faca upgrade para criar mais."
            );
        }
    }

    public function ensureCanEditAccount(User $user, Account $account): void
    {
        if (!$this->canEditAccount($user, $account)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_accounts');
            throw new SubscriptionLimitExceededException(
                "Seu plano {$subscription->plan_name} permite editar apenas as {$limit} contas mais antigas."
            );
        }
    }

    public function ensureCanEditPortfolio(User $user, Portfolio $portfolio): void
    {
        if (!$this->canEditPortfolio($user, $portfolio)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_portfolios');
            throw new SubscriptionLimitExceededException(
                "Seu plano {$subscription->plan_name} permite editar apenas as {$limit} carteiras mais antigas."
            );
        }
    }

    public function ensureCanEditComposition(User $user, Composition $composition): void
    {
        if (!$this->canEditComposition($user, $composition)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_compositions');
            throw new SubscriptionLimitExceededException(
                "Seu plano {$subscription->plan_name} permite editar apenas as {$limit} composicoes mais antigas da carteira."
            );
        }
    }

    public function ensureCanEditPosition(User $user, Consolidated $consolidated): void
    {
        if (!$this->canEditPosition($user, $consolidated)) {
            $subscription = $this->getActiveSubscription($user);
            $limit = $subscription->getLimit('max_positions');
            throw new SubscriptionLimitExceededException(
                "Seu plano {$subscription->plan_name} permite editar apenas as {$limit} posicoes mais antigas."
            );
        }
    }

    public function updateUsage(User $user): void
    {
        $subscription = $this->getActiveSubscription($user);
        $usage = $this->getOrCreateUsage($user, $subscription);
        $usage->recalculate();
    }

    public function ensureUserHasSubscription(User $user): UserSubscription
    {
        $subscription = $user->subscriptions()->active()->first();

        if (!$subscription) {
            $subscription = $this->createFreeSubscription($user);
        }

        return $subscription;
    }

    public function createFreeSubscription(User $user): UserSubscription
    {
        $freePlan = SubscriptionPlan::where('slug', 'free')
            ->with('configs')
            ->firstOrFail();

        $subscription = UserSubscription::create([
            'user_id' => $user->id,
            'subscription_plan_id' => $freePlan->id,
            'plan_name' => $freePlan->name,
            'plan_slug' => $freePlan->slug,
            'price_monthly' => $freePlan->price_monthly,
            'limits_snapshot' => $freePlan->getLimitsArray(),
            'features_snapshot' => $freePlan->getFeaturesArray(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => null,
            'is_paid' => true,
        ]);

        $this->getOrCreateUsage($user, $subscription);

        return $subscription;
    }

    public function createSubscriptionFromPlan(User $user, SubscriptionPlan $plan, array $extraData = []): UserSubscription
    {
        $plan->load('configs');

        $isFree = $plan->slug === 'free';

        $data = array_merge([
            'user_id' => $user->id,
            'subscription_plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_slug' => $plan->slug,
            'price_monthly' => $plan->price_monthly,
            'limits_snapshot' => $plan->getLimitsArray(),
            'features_snapshot' => $plan->getFeaturesArray(),
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $isFree ? null : ($extraData['ends_at'] ?? null),
            'is_paid' => $isFree ? true : false,
        ], $extraData);

        $subscription = UserSubscription::create($data);
        $this->getOrCreateUsage($user, $subscription);

        return $subscription;
    }

    private function getActiveSubscription(User $user): UserSubscription
    {
        $subscription = $user->subscriptions()
            ->active()
            ->orderByDesc('is_paid')
            ->orderByDesc('created_at')
            ->first();

        if (!$subscription) {
            $subscription = $this->createFreeSubscription($user);
        }

        return $subscription;
    }

    private function getOrCreateUsage(User $user, UserSubscription $subscription): UserSubscriptionUsage
    {
        $usage = UserSubscriptionUsage::where('user_id', $user->id)->first();

        if (!$usage) {
            $usage = UserSubscriptionUsage::create([
                'user_id' => $user->id,
                'user_subscription_id' => $subscription->id,
                'current_portfolios' => 0,
                'current_compositions' => 0,
                'current_positions' => 0,
                'current_accounts' => 0,
            ]);
        }

        if ($usage->user_subscription_id !== $subscription->id) {
            $usage->user_subscription_id = $subscription->id;
            $usage->save();
        }

        if (!$usage->last_calculated_at) {
            $usage->recalculate();
        }

        return $usage;
    }

    private function getAllowedIds(Builder $query, ?int $limit): array
    {
        if ($limit === null) {
            return [];
        }

        return $query->orderBy('created_at')
            ->limit((int) $limit)
            ->pluck('id')
            ->all();
    }
}
