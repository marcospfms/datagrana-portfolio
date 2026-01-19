<?php

namespace App\Policies;

use App\Models\Consolidated;
use App\Models\User;

class ConsolidatedPolicy
{
    public function view(User $user, Consolidated $consolidated): bool
    {
        return $consolidated->account->user_id === $user->id;
    }

    public function update(User $user, Consolidated $consolidated): bool
    {
        return $consolidated->account->user_id === $user->id;
    }

    public function delete(User $user, Consolidated $consolidated): bool
    {
        return $consolidated->account->user_id === $user->id;
    }
}
