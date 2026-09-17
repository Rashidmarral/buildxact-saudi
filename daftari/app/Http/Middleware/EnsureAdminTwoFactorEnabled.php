<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security audit finding D-6: platform admin accounts (super_admin,
 * admin_staff) could operate on password-only authentication
 * indefinitely — no middleware ever checked whether 2FA was enabled,
 * unlike company accounts (opt-in) or the login flow (only challenged
 * if already enabled). Given the blast radius of one of these accounts
 * (every tenant's financial data; super_admin can also impersonate any
 * customer), a single leaked/phished password was a full platform
 * compromise. Applied to the whole /admin route group, this redirects
 * any admin without 2FA enabled to set it up before reaching anything
 * else in the panel.
 *
 * Excludes, by route name, the 2FA setup screen/actions themselves and
 * the password-confirmation screen (the same self-exclusion pattern
 * EnsurePasswordConfirmed's own routes need, for the same reason:
 * guarding the screen that satisfies the requirement would be an
 * infinite redirect) and the admin logout route (an admin without 2FA
 * must still be able to sign out).
 */
class EnsureAdminTwoFactorEnabled
{
    private const EXCLUDED_ROUTES = [
        'admin.settings.two-factor',
        'admin.settings.two-factor.confirm',
        'admin.settings.two-factor.disable',
        'admin.settings.two-factor.recovery-codes',
        'admin.password.confirm',
        'admin.password.confirm.store',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->hasTwoFactorEnabled() && ! $request->routeIs(...self::EXCLUDED_ROUTES)) {
            return redirect()->route('admin.settings.two-factor')->with('status', __(
                'Two-factor authentication is required for platform admin accounts. Set it up to continue.'
            ));
        }

        return $next($request);
    }
}
