<?php

namespace App\Support;

class UserAgentSummary
{
    /**
     * Turns a raw User-Agent string into a short "Browser on OS" label for
     * the active-sessions list. Deliberately simple substring matching
     * rather than a parser dependency — this only needs to be good enough
     * for a user to recognize their own devices, not fully accurate.
     */
    public static function describe(?string $userAgent): string
    {
        if (! $userAgent) {
            return __('Unknown device');
        }

        $os = match (true) {
            str_contains($userAgent, 'iPhone') => 'iPhone',
            str_contains($userAgent, 'iPad') => 'iPad',
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Macintosh') || str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => null,
        };

        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') || str_contains($userAgent, 'Opera') => 'Opera',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'CriOS/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => null,
        };

        if ($browser && $os) {
            return __(':browser on :os', ['browser' => $browser, 'os' => $os]);
        }

        if ($browser) {
            return $browser;
        }

        if ($os) {
            return $os;
        }

        return __('Unknown device');
    }
}
