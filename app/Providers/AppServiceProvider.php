<?php

namespace App\Providers;

use App\Modules\Deposits\Models\Deposit;
use App\Modules\Deposits\Policies\DepositPolicy;
use App\Modules\Notifications\Models\UserNotification;
use App\Modules\Notifications\Policies\UserNotificationPolicy;
use App\Modules\Wallets\Models\Wallet;
use App\Modules\Wallets\Policies\WalletPolicy;
use App\Modules\Withdrawals\Models\Withdrawal;
use App\Modules\Withdrawals\Policies\WithdrawalPolicy;
use Illuminate\Database\Events\ConnectionEstablished;
use Illuminate\Support\Facades\Event;
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
        Gate::policy(UserNotification::class, UserNotificationPolicy::class);

        $this->configureDatabaseTimeouts();
    }

    /**
     * Apply PostgreSQL server-side timeouts so a stuck query or an abandoned
     * transaction cannot hold locks indefinitely. Both default to 0 (disabled)
     * and are enabled per environment via DB_STATEMENT_TIMEOUT /
     * DB_IDLE_TRANSACTION_TIMEOUT (milliseconds).
     */
    private function configureDatabaseTimeouts(): void
    {
        Event::listen(ConnectionEstablished::class, function (ConnectionEstablished $event): void {
            $config = $event->connection->getConfig();

            $statementTimeout = (int) ($config['statement_timeout'] ?? 0);
            if ($statementTimeout > 0) {
                $event->connection->statement("SET statement_timeout = {$statementTimeout}");
            }

            $idleTimeout = (int) ($config['idle_in_transaction_session_timeout'] ?? 0);
            if ($idleTimeout > 0) {
                $event->connection->statement("SET idle_in_transaction_session_timeout = {$idleTimeout}");
            }
        });
    }
}
