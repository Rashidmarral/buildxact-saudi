<?php

use App\Exceptions\PdfRenderingException;
use App\Exceptions\PeriodLockedException;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureAdminPermission;
use App\Http\Middleware\EnsureCompanyActive;
use App\Http\Middleware\EnsureImpersonationNotExpired;
use App\Http\Middleware\EnsurePasswordConfirmed;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePhoneVerified;
use App\Http\Middleware\EnsurePlanFeature;
use App\Http\Middleware\EnsureRegistrationOpen;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureUserBelongsToCompany;
use App\Http\Middleware\EnsureWithinApiLimit;
use App\Http\Middleware\PrepareInstallerEnvironment;
use App\Http\Middleware\PreventDemoDestruction;
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
        $middleware->web(prepend: [
            PrepareInstallerEnvironment::class,
        ]);

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
            'company.member' => EnsureUserBelongsToCompany::class,
            'permission' => EnsurePermission::class,
            'admin.permission' => EnsureAdminPermission::class,
            'feature' => EnsurePlanFeature::class,
            'registration.open' => EnsureRegistrationOpen::class,
            'phone.verified' => EnsurePhoneVerified::class,
            'abilities' => CheckAbilities::class,
            'client.portal' => \App\Http\Middleware\EnsureClientPortalSession::class,
            'password.confirm.admin' => EnsurePasswordConfirmed::class,
            'api.limit' => EnsureWithinApiLimit::class,
            'demo.guard' => PreventDemoDestruction::class,
            'impersonation.timeout' => EnsureImpersonationNotExpired::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PdfRenderingException $e, $request) {
            report($e);

            return back()->withErrors(['pdf' => $e->getMessage()]);
        });

        $exceptions->render(function (PeriodLockedException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return back()->withErrors(['period' => $e->getMessage()]);
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
