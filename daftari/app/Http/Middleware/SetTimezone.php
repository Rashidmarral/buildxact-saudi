<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes "Default timezone" actually shift what the app shows, without
 * touching every date display individually: PHP's date functions — and
 * Carbon, when a value carries no explicit offset (which is exactly how
 * Eloquent's naive "Y-m-d H:i:s" datetime columns come back) — fall back to
 * date_default_timezone_get(). Setting it here, before any date is
 * touched this request, makes now() and every ->format() call on a
 * DB-sourced timestamp render in the resolved timezone app-wide.
 *
 * Resolution order: the logged-in user's company timezone, else the
 * platform default, else whatever config/app.php already has — so a
 * fresh install with nothing configured behaves exactly as before this
 * setting existed.
 */
class SetTimezone
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $timezone = $request->user()?->company?->timezone
                ?: Setting::get('general_default_timezone', config('app.timezone'));

            if ($timezone && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                date_default_timezone_set($timezone);
                config(['app.timezone' => $timezone]);
            }
        } catch (\Throwable) {
            // Settings/companies table not migrated yet (fresh install
            // running an early artisan command) — leave the configured
            // default timezone alone rather than breaking the request.
        }

        return $next($request);
    }
}
