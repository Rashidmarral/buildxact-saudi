<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keyed by email+IP (not IP alone) so one attacker can't lock out
        // every other user sharing that IP (offices, NAT, mobile carriers),
        // while still throttling credential-stuffing against a single
        // account from many IPs.
        RateLimiter::for('login', function ($request) {
            $key = Str::transliterate(Str::lower((string) $request->input('email'))).'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Looser and IP-only: this endpoint doesn't reveal whether the
        // email exists, so the abuse case is email-bombing a victim's inbox
        // rather than credential stuffing.
        RateLimiter::for('password-email', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });
    }
}
