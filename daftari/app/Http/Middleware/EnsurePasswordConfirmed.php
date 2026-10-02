<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Step-up re-authentication for admin actions with a large blast radius
 * (creating/removing platform admins, impersonating a company, changing a
 * company's plan or suspension state) — a stolen or left-open admin
 * session shouldn't be enough on its own to take one of these actions.
 * Mirrors Laravel Fortify's password-confirmation window: once confirmed,
 * the timestamp is good for CONFIRMATION_MINUTES before it's asked again.
 */
class EnsurePasswordConfirmed
{
    public const CONFIRMATION_MINUTES = 15;

    public function handle(Request $request, Closure $next): Response
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        if (! $confirmedAt || now()->timestamp - $confirmedAt > self::CONFIRMATION_MINUTES * 60) {
            // The guarded routes here are all POST/DELETE actions with no
            // page of their own to render — send the admin back to
            // wherever they clicked the action from (not the action URL
            // itself, which wouldn't accept a plain GET) so they can
            // re-click it once confirmed, now within the fresh window.
            $request->session()->put('url.intended', url()->previous());

            return redirect()->route('admin.password.confirm');
        }

        return $next($request);
    }
}
