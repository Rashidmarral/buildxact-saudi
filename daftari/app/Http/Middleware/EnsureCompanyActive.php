<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Company::isOperational() also covers the automatic dunning
        // ladder reaching 'suspended' and a subscription lapsing to a
        // terminal state — previously this only checked manual
        // (Company::status) suspension (security audit finding D-0).
        if ($user && $user->company && ! $user->company->isOperational()) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been suspended. Please contact support.',
            ]);
        }

        return $next($request);
    }
}
