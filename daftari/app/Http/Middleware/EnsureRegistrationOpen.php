<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates /register behind the "Allow registrations" platform setting — off
 * by default means open (matches pre-existing behavior), so a fresh
 * install with no Setting row keeps letting new companies sign up.
 */
class EnsureRegistrationOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Setting::getBool('general_allow_registrations', true)) {
            return $next($request);
        }

        return response()->view('errors.registration-closed', [], 403);
    }
}
