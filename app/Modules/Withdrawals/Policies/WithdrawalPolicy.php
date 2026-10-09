<?php

namespace App\Modules\Withdrawals\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Withdrawals\Models\Withdrawal;

class WithdrawalPolicy
{
    public function view(User $user, Withdrawal $withdrawal): bool
    {
        if ($withdrawal->user_id === $user->id) {
            return true;
        }

        return $user->hasAnyPermission(['withdrawals.approve', 'withdrawals.process', 'audit.view']);
    }

    public function cancel(User $user, Withdrawal $withdrawal): bool
    {
        return $withdrawal->user_id === $user->id;
    }
}
