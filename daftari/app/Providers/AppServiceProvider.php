<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\DbOverlayTranslationLoader;
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
        // Wraps the framework's file-based translation loader (JSON/PHP
        // lang files) with one that lets an admin-entered Translation row
        // override any given key — see DbOverlayTranslationLoader. Using
        // extend() rather than replacing the binding outright means this
        // works correctly whether 'translation.loader' gets resolved before
        // or after this call (Illuminate\Translation\TranslationServiceProvider
        // is deferred, so it's usually the latter).
        $this->app->extend('translation.loader', fn ($loader) => new DbOverlayTranslationLoader($loader));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureStorageDriver();

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

        // Same reasoning as password-email: this doesn't reveal whether the
        // address matches a client, so the abuse case is inbox-bombing a
        // client, not credential stuffing (there's no credential to stuff —
        // it's a magic link).
        RateLimiter::for('client-portal-login', function ($request) {
            return Limit::perMinute(3)->by($request->ip());
        });

        // The pending user hasn't authenticated yet at this point (that's
        // the whole reason there's a second factor), so this can't be
        // keyed by Auth::id() — the session-stored pending user id is the
        // closest equivalent, still narrow enough to not let one account
        // exhaust another's attempts.
        RateLimiter::for('two-factor', function ($request) {
            $key = $request->session()->get('two_factor_user_id', 'guest').'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // Unlike the login-time challenge above, this one confirms a TOTP
        // code for an already-authenticated user turning 2FA on, so it can
        // be keyed by their own id — no risk of one account's attempts
        // exhausting another's.
        RateLimiter::for('two-factor-confirm', function ($request) {
            return Limit::perMinute(5)->by($request->user()?->id ?: $request->ip());
        });

        // Guards the step-up re-authentication screen every dangerous admin
        // action funnels through (password.confirm.admin middleware) —
        // without this an attacker holding a hijacked admin session could
        // brute-force the account's own password with no limit at all.
        // Keyed by user+IP, matching login's reasoning.
        RateLimiter::for('admin-password-confirm', function ($request) {
            $key = ($request->user()?->id ?: 'guest').'|'.$request->ip();

            return Limit::perMinute(5)->by($key);
        });

        // The public API (routes/api.php) is otherwise only gated by
        // EnsureWithinApiLimit, a per-company *monthly* usage cap tied to
        // the plan — nothing stopped a single leaked or malicious token
        // from hammering it at unlimited requests/second within that
        // monthly budget. Keyed by the authenticated user (a Sanctum
        // token always resolves one), not IP, so multiple staff sharing
        // an office connection don't throttle each other.
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }

    /**
     * When the admin picks S3 in Platform Settings → Storage, swap the
     * 'public' disk (every Storage::disk('public') upload call in the app —
     * branding logos, attachments, letterheads — targets this one name)
     * over to the s3 driver, keyed by the encrypted credentials from the
     * Setting table instead of the .env AWS_* vars. Runs before any disk is
     * resolved, so every existing call site picks it up with no code
     * changes. Falls through to the default 'local' driver from
     * config/filesystems.php untouched if S3 isn't configured — a fresh
     * install behaves exactly as before this setting existed.
     */
    private function configureStorageDriver(): void
    {
        try {
            if (Setting::get('storage_driver', 'local') !== 's3') {
                return;
            }

            $key = Setting::get('storage_s3_key');
            $secret = Setting::get('storage_s3_secret');
            $bucket = Setting::get('storage_s3_bucket');
            $region = Setting::get('storage_s3_region');

            if (! $key || ! $secret || ! $bucket || ! $region) {
                return;
            }

            config([
                'filesystems.disks.public' => [
                    'driver' => 's3',
                    'key' => $key,
                    'secret' => $secret,
                    'region' => $region,
                    'bucket' => $bucket,
                    'endpoint' => Setting::get('storage_s3_endpoint') ?: null,
                    'url' => Setting::get('storage_s3_url') ?: null,
                    'use_path_style_endpoint' => (bool) Setting::get('storage_s3_endpoint'),
                    'visibility' => 'public',
                    'throw' => false,
                    'report' => false,
                ],
            ]);
        } catch (\Throwable) {
            // Settings table not migrated yet (fresh install running an
            // early artisan command) — behave as if S3 isn't configured
            // rather than breaking every request.
        }
    }
}
