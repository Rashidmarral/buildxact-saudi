<?php

use App\Exceptions\PdfRenderingException;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureCompanyActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePhoneVerified;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureRegistrationOpen;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\SetTimezone;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetTimezone::class,
            SetLocale::class,
            CheckMaintenanceMode::class,
        ]);

        // Payment providers POST here with no Laravel session/CSRF token
        // of their own — authenticity is verified inside the controller
        // via each gateway's own signature scheme instead (see
        // PaymentGatewayDriver::verifyWebhook).
        $middleware->validateCsrfTokens(except: [
            'payments/webhook/*',
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'company.active' => EnsureCompanyActive::class,
            'permission' => EnsurePermission::class,
            'admin.permission' => EnsureAdminPermission::class,
            'feature' => EnsurePlanFeature::class,
            'registration.open' => EnsureRegistrationOpen::class,
            'phone.verified' => EnsurePhoneVerified::class,
            'abilities' => CheckAbilities::class,
            'client.portal' => \App\Http\Middleware\EnsureClientPortalSession::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PdfRenderingException $e, $request) {
            report($e);

            return back()->withErrors(['pdf' => $e->getMessage()]);
        });

        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            if ($request->expectsJson()) {
                return null;
            }

            $seconds = $e->getHeaders()['Retry-After'] ?? 60;

            return back()->withErrors([
                'email' => __('Too many attempts. Please wait :seconds seconds and try again.', ['seconds' => $seconds]),
            ])->onlyInput('email');
        });

        // Only registers reporting to Sentry when SENTRY_LARAVEL_DSN is set
        // (see config/sentry.php) — a no-op locally/in any environment that
        // hasn't been given a real DSN.
        Integration::handles($exceptions);
    })->create();
