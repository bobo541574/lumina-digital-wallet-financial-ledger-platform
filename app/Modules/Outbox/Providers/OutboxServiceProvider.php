<?php

namespace App\Modules\Outbox\Providers;

use App\Modules\Outbox\Console\ProcessOutboxCommand;
use Illuminate\Support\ServiceProvider;

class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessOutboxCommand::class,
            ]);
        }
    }
}
