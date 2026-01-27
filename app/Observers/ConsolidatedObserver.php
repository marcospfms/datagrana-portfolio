<?php

namespace App\Observers;

use App\Models\Consolidated;
use App\Models\UserSubscriptionUsage;

class ConsolidatedObserver
{
    public function created(Consolidated $consolidated): void
    {
        if ($consolidated->closed) {
            return;
        }

        $userId = $consolidated->account?->user_id;

        if (!$userId) {
            return;
        }

        $usage = UserSubscriptionUsage::where('user_id', $userId)->first();

        if ($usage) {
                $usage->incrementCounter('current_positions');
        }
    }

    public function updated(Consolidated $consolidated): void
    {
        if (!$consolidated->wasChanged('closed')) {
            return;
        }

        $userId = $consolidated->account?->user_id;

        if (!$userId) {
            return;
        }

        $usage = UserSubscriptionUsage::where('user_id', $userId)->first();

        if (!$usage) {
            return;
        }

        if ($consolidated->closed) {
                    $usage->decrementCounter('current_positions');
            return;
        }

                    $usage->incrementCounter('current_positions');
    }

    public function deleted(Consolidated $consolidated): void
    {
        if ($consolidated->closed) {
            return;
        }

        $userId = $consolidated->account?->user_id;

        if (!$userId) {
            return;
        }

        $usage = UserSubscriptionUsage::where('user_id', $userId)->first();

        if ($usage) {
                $usage->decrementCounter('current_positions');
        }
    }
}
