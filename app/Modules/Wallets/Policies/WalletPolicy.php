<?php

namespace App\Modules\Wallets\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Wallets\Models\Wallet;

class WalletPolicy
{
    public function view(User $user, Wallet $wallet): bool
    {
        return $this->owns($user, $wallet) || $user->hasPermissionTo('wallets.manage');
    }

    public function operate(User $user, Wallet $wallet): bool
    {
        return $this->owns($user, $wallet);
    }

    private function owns(User $user, Wallet $wallet): bool
    {
        return $user->id === $wallet->user_id;
    }
}
