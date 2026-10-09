<?php

namespace App\Modules\Ledger\Providers;

use App\Modules\Ledger\Contracts\LedgerPostingServiceInterface;
use App\Modules\Ledger\Services\LedgerPostingService;
use Illuminate\Support\ServiceProvider;

class LedgerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(LedgerPostingServiceInterface::class, LedgerPostingService::class);
    }

    public function boot(): void
    {
        //
    }
}
