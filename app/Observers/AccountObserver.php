<?php

namespace App\Observers;

use App\Models\Account;
use App\Models\UserSubscriptionUsage;

class AccountObserver
{
    public function created(Account $account): void
    {
        $usage = UserSubscriptionUsage::where('user_id', $account->user_id)->first();

        if ($usage) {
            $usage->incrementCounter('current_accounts');
        }
    }

    public function deleted(Account $account): void
    {
        $usage = UserSubscriptionUsage::where('user_id', $account->user_id)->first();

        if ($usage) {
            $usage->decrementCounter('current_accounts');
        }
    }
}
