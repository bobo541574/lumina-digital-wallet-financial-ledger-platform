<?php

namespace App\Modules\Identity\Providers;

use App\Modules\Identity\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class IdentityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Let any permission name be used directly as a Gate ability, e.g.
        // Gate::allows('wallets.view'). Returning null falls through to other
        // checks (policies, gates) when the user lacks the permission.
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasPermissionTo($ability) ? true : null;
        });
    }
}
