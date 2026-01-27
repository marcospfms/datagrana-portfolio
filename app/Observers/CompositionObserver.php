<?php

namespace App\Observers;

use App\Models\Composition;
use App\Models\UserSubscriptionUsage;

class CompositionObserver
{
    public function created(Composition $composition): void
    {
        $userId = $composition->portfolio?->user_id;

        if (!$userId) {
            return;
        }

        $usage = UserSubscriptionUsage::where('user_id', $userId)->first();

        if ($usage) {
            $usage->incrementCounter('current_compositions');
        }
    }

    public function deleted(Composition $composition): void
    {
        $userId = $composition->portfolio?->user_id;

        if (!$userId) {
            return;
        }

        $usage = UserSubscriptionUsage::where('user_id', $userId)->first();

        if ($usage) {
            $usage->decrementCounter('current_compositions');
        }
    }
}
