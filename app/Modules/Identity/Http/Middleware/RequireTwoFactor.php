<?php

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\Enums\Role;
use App\Modules\Identity\Exceptions\MfaRequiredException;
use App\Modules\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks privileged staff from using admin endpoints until they have enrolled
 * and confirmed TOTP. Gated by `security.two_factor.admin_required`, which is
 * disabled by default so non-MFA environments keep working.
 */
class RequireTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('security.two_factor.admin_required')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user instanceof User && $this->isPrivileged($user) && ! $user->hasTwoFactorEnabled()) {
            throw new MfaRequiredException;
        }

        return $next($request);
    }

    private function isPrivileged(User $user): bool
    {
        return $user->hasRole([Role::Admin, Role::FinanceOperator]);
    }
}
