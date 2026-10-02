<?php

namespace App\Http\Controllers\User\Concerns;

use Illuminate\Http\Request;

/**
 * Every paginated list in the app used a fixed page size with no way for
 * a user to change it — a real gap flagged when auditing pagination
 * coverage. This lets an index() accept ?per_page= (validated against a
 * fixed allowlist, matching the options rendered by
 * partials/pagination.blade.php) while keeping its own sensible default
 * when the param is absent or tampered with.
 */
trait ResolvesPerPage
{
    private const ALLOWED_PER_PAGE = [10, 20, 25, 50, 100];

    protected function resolvePerPage(Request $request, int $default = 20): int
    {
        $requested = (int) $request->query('per_page', $default);

        return in_array($requested, self::ALLOWED_PER_PAGE, true) ? $requested : $default;
    }
}
