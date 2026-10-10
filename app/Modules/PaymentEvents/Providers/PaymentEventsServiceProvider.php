<?php

namespace App\Modules\PaymentEvents\Providers;

use App\Modules\PaymentEvents\Console\EnsurePartitionsCommand;
use Illuminate\Support\ServiceProvider;

class PaymentEventsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                EnsurePartitionsCommand::class,
            ]);
        }
    }
}
