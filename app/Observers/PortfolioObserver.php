<?php

namespace App\Observers;

use App\Models\Portfolio;
use App\Models\UserSubscriptionUsage;

class PortfolioObserver
{
    public function created(Portfolio $portfolio): void
    {
        $usage = UserSubscriptionUsage::where('user_id', $portfolio->user_id)->first();

        if ($usage) {
            $usage->incrementCounter('current_portfolios');
        }
    }

    public function deleted(Portfolio $portfolio): void
    {
        $usage = UserSubscriptionUsage::where('user_id', $portfolio->user_id)->first();

        if ($usage) {
            $usage->decrementCounter('current_portfolios');
        }
    }
}
