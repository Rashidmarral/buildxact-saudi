<?php

use App\Exceptions\PdfRenderingException;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnsureCompanyActive;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
            CheckMaintenanceMode::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
            'company.active' => EnsureCompanyActive::class,
            'permission' => EnsurePermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PdfRenderingException $e, $request) {
            report($e);

            return back()->withErrors(['pdf' => $e->getMessage()]);
        });

        // Only registers reporting to Sentry when SENTRY_LARAVEL_DSN is set
        // (see config/sentry.php) — a no-op locally/in any environment that
        // hasn't been given a real DSN.
        Integration::handles($exceptions);
    })->create();
