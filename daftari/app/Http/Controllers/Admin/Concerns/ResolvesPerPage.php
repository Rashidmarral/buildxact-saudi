<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Http\Request;

/**
 * Admin-side mirror of App\Http\Controllers\User\Concerns\ResolvesPerPage
 * — lets an admin index() accept ?per_page= (validated against the same
 * fixed allowlist rendered by partials/pagination.blade.php) while
 * keeping its own sensible default when the param is absent or tampered
 * with.
 */
trait ResolvesPerPage
{
    private const ALLOWED_PER_PAGE = [10, 20, 25, 50, 100];

    protected function resolvePerPage(Request $request, int $default = 20, string $key = 'per_page'): int
    {
        $requested = (int) $request->query($key, $default);

        return in_array($requested, self::ALLOWED_PER_PAGE, true) ? $requested : $default;
    }
}
