<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit finding MEDIUM-18: an admin impersonation session (Auth::login()
 * swapped to the tenant user — see Admin\CompanyController::impersonate())
 * previously never expired on its own, bounded only by the underlying
 * browser session's own lifetime. A support engineer who forgot to click
 * "Stop impersonating" — or an admin session left open on a shared machine
 * — could stay logged in as a customer indefinitely. This forces it back
 * to the admin after IMPERSONATION_TIMEOUT_MINUTES of elapsed wall-clock
 * time, regardless of activity.
 */
class EnsureImpersonationNotExpired
{
    private const TIMEOUT_MINUTES = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $impersonatorId = $request->session()->get('impersonator_id');
        $startedAt = $request->session()->get('impersonation_started_at');

        if ($impersonatorId && $startedAt && now()->timestamp - (int) $startedAt > self::TIMEOUT_MINUTES * 60) {
            $admin = User::withoutGlobalScopes()->find($impersonatorId);

            if ($admin && $admin->isSuperAdmin()) {
                // Recorded before the session/auth swap below, with no
                // explicit actor, so AuditLog::record()'s own impersonation
                // detection (still sees session('impersonator_id') and the
                // still-impersonated Auth::user()) attributes it to the
                // admin and stamps impersonated_user_id — the same path
                // every other action taken during this session went through.
                AuditLog::record(
                    'company.impersonation_expired',
                    Auth::user()?->company,
                    __('Impersonation session automatically ended after :minutes minutes', ['minutes' => self::TIMEOUT_MINUTES]),
                );

                $request->session()->forget(['impersonator_id', 'impersonation_started_at']);
                Auth::login($admin);

                return redirect()->route('admin.dashboard')->with('status', __('Your impersonation session expired after :minutes minutes and was ended automatically.', ['minutes' => self::TIMEOUT_MINUTES]));
            }

            // Admin account gone/demoted — can't restore it, just end the
            // impersonated session rather than leaving a stale timestamp.
            $request->session()->forget(['impersonator_id', 'impersonation_started_at']);
        }

        return $next($request);
    }
}
