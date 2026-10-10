<?php

namespace App\Modules\Reconciliation\Providers;

use App\Modules\Reconciliation\Console\AlertReconciliationCommand;
use App\Modules\Reconciliation\Console\RunReconciliationCommand;
use Illuminate\Support\ServiceProvider;

class ReconciliationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunReconciliationCommand::class,
                AlertReconciliationCommand::class,
            ]);
        }
    }
}
