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

        if ($user && $user->company && $user->company->isSuspended()) {
            Auth::logout();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been suspended. Please contact support.',
            ]);
        }

        return $next($request);
    }
}
