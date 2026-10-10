<?php

namespace App\Modules\Shared\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(
            (int) config('security.rate_limits.auth'),
        )->by($request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(
            (int) config('security.rate_limits.webhooks'),
        )->by(($request->route('provider') ?? 'unknown').'|'.$request->ip()));

        RateLimiter::for('money', fn (Request $request) => Limit::perMinute(
            (int) config('security.rate_limits.money'),
        )->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('admin', fn (Request $request) => Limit::perMinute(
            (int) config('security.rate_limits.admin'),
        )->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }
}
