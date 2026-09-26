<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Support\Locales;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        // A visitor's own explicit choice (the language switcher, stored in
        // session) always wins; absent that, the platform default
        // (Platform Settings → General → "Default language") applies, so
        // that setting genuinely drives what a fresh visitor sees instead
        // of only ever being read back on the settings form itself.
        $locale = $request->session()->get('locale')
            ?? $this->platformDefault()
            ?? config('app.locale');

        if (! Locales::isValid($locale)) {
            $locale = 'en';
        }

        App::setLocale($locale);

        return $next($request);
    }

    private function platformDefault(): ?string
    {
        try {
            return Setting::get('general_default_language');
        } catch (\Throwable) {
            // Settings table not migrated yet (fresh install running an
            // early artisan command) — fall through to config('app.locale').
            return null;
        }
    }
}
