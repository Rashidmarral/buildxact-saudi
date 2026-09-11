<?php

namespace App\Support\Installer;

use Illuminate\Support\Facades\File;

/**
 * Guards the /install wizard (Module 24) from ever running twice by
 * accident. A successful Step 6 writes a small marker file — never
 * credentials, just who/when — and every installer route is refused once
 * it exists (see App\Http\Middleware\RedirectIfInstalled). Re-enabling is
 * only ever a deliberate CLI action (`php artisan installer:enable`), not
 * anything reachable from the web.
 */
class InstallerLock
{
    public static function path(): string
    {
        return storage_path('app/installed.lock');
    }

    public static function isInstalled(): bool
    {
        return File::exists(self::path());
    }

    /**
     * Content is diagnostic only (when, and by which admin email) — never
     * database credentials or passwords, so this file is safe to leave on
     * disk indefinitely and safe to include in a support bundle.
     */
    public static function lock(string $adminEmail): void
    {
        File::ensureDirectoryExists(dirname(self::path()));
        File::put(self::path(), json_encode([
            'installed_at' => now()->toIso8601String(),
            'admin_email' => $adminEmail,
        ], JSON_PRETTY_PRINT));
    }

    public static function unlock(): void
    {
        File::delete(self::path());
    }
}
