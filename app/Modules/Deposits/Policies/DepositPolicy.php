<?php

namespace App\Modules\Deposits\Policies;

use App\Modules\Deposits\Models\Deposit;
use App\Modules\Identity\Models\User;

class DepositPolicy
{
    public function view(User $user, Deposit $deposit): bool
    {
        if ($deposit->user_id === $user->id) {
            return true;
        }

        return $user->hasAnyPermission(['deposits.approve', 'deposits.confirm', 'reconciliation.view']);
    }
}
