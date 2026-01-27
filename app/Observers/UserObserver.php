<?php

namespace App\Observers;

use App\Models\User;
use App\Services\SubscriptionLimitService;

class UserObserver
{
    public function __construct(
        protected SubscriptionLimitService $limitService
    ) {}

    public function created(User $user): void
    {
        $this->limitService->ensureUserHasSubscription($user);
    }
}
