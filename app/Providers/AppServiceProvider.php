<?php

namespace App\Providers;

use App\Modules\Deposits\Models\Deposit;
use App\Modules\Deposits\Policies\DepositPolicy;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Policies\WalletPolicy;
use App\Modules\Withdrawals\Models\Withdrawal;
use App\Modules\Withdrawals\Policies\WithdrawalPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(Deposit::class, DepositPolicy::class);
        Gate::policy(Withdrawal::class, WithdrawalPolicy::class);
    }
}
