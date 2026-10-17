<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\DbOverlayTranslationLoader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        $this->configureMailDriver();

        // Go-live audit finding: nothing forced URL generation (redirects,
        // signed links, asset() calls) onto https in production — a
        // company reached over plain http would get http:// links back for
        // password resets, team invites, and payable-invoice links, and
        // the "secure" session cookie derived from APP_URL's scheme (see
        // config/session.php) would silently stop being sent. Left off in
        // local/testing so `php artisan serve` and the test suite are
        // unaffected.
        if (! $this->app->environment(['local', 'testing'])) {
            URL::forceScheme('https');
        }

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

        // mPDF rendering is CPU-heavy (font shaping, embedded images) and
        // invoice/quotation email sends both render a PDF and make an
        // outbound SMTP call — neither had any throttle at all, so a
        // scripted "download PDF"/"send email" loop (an authenticated
        // user, or a leaked API token) had no limit besides the plan's
        // monthly document cap. Keyed per-user, not IP, for the same
        // shared-office reason as 'api' above.
        RateLimiter::for('pdf', function ($request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // The public, token-gated pay-link/quotation-view PDF routes have
        // no authenticated user to key on, and are reachable by anyone who
        // has (or guesses/leaks) the link — a tighter, IP-keyed limit here.
        RateLimiter::for('public-pdf', function ($request) {
            return Limit::perMinute(10)->by($request->ip());
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

    /**
     * When a super admin turns on Platform Settings → Email, outgoing mail
     * (welcome emails, invoice/quotation sends, payment receipts, overdue
     * reminders, team invites — everything routed through Mail:: or a
     * queued Mailable) uses these DB-stored SMTP credentials instead of
     * whatever MAIL_MAILER/.env happens to be set to. Runs before any
     * mailer is resolved, so no call site needs to change. Left off (the
     * default), the app behaves exactly as it did before this setting
     * existed — including MAIL_MAILER's own default of "log", which is
     * why the settings screen calls this out explicitly as the most common
     * thing an operator forgets before go-live.
     */
    private function configureMailDriver(): void
    {
        try {
            if (! Setting::getBool('mail_smtp_enabled')) {
                return;
            }

            $host = Setting::get('mail_smtp_host');
            $fromAddress = Setting::get('mail_from_address');

            if (! $host || ! $fromAddress) {
                return;
            }

            $encryption = Setting::get('mail_smtp_encryption', 'tls');

            config([
                'mail.default' => 'smtp',
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => (int) Setting::get('mail_smtp_port', 587),
                'mail.mailers.smtp.encryption' => $encryption === 'none' ? null : $encryption,
                'mail.mailers.smtp.username' => Setting::get('mail_smtp_username') ?: null,
                'mail.mailers.smtp.password' => Setting::get('mail_smtp_password') ?: null,
                'mail.from.address' => $fromAddress,
                'mail.from.name' => Setting::get('mail_from_name', config('mail.from.name')),
            ]);
        } catch (\Throwable) {
            // Settings table not migrated yet — fall back to .env's mail
            // config rather than breaking every request (including the
            // artisan commands that run migrations in the first place).
        }
    }
}
