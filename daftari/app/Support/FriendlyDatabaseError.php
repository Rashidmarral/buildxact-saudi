<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * "please fix this if something required that missing please handle it
 * with more professional show error that's required or something not
 * for this for the whole app" — a NOT NULL/unique/foreign-key
 * constraint violation reaching the database is always, by definition,
 * a gap in application-layer validation somewhere (a required field
 * that should have been caught by a form rule, a duplicate the UI
 * should have blocked, a delete that should have been refused earlier)
 * — see the Employee transport_allowance fix this was built alongside
 * for exactly that kind of gap. This is the app-wide safety net for
 * whichever one of those gaps turns up next, translating the raw
 * SQLSTATE/engine message every such violation produces into one
 * sentence a non-technical user can actually act on, instead of a raw
 * "SQLSTATE[23000]: ..." trace — regardless of which database engine
 * is running (MySQL in production, SQLite in tests, both covered
 * below) or whether APP_DEBUG happens to be on.
 *
 * Deliberately narrow: only the three well-understood integrity-
 * constraint families below are translated. Anything else returns
 * null, leaving Laravel's own default handling (and APP_DEBUG) in
 * charge — a query timeout or a genuine SQL syntax error is a bug to
 * fix and see in full, not something to paper over with a friendly
 * guess.
 */
class FriendlyDatabaseError
{
    public static function describe(QueryException $e): ?string
    {
        $message = $e->getMessage();

        if ($column = self::match($message, [
            "/Column '(?<col>[\\w.]+)' cannot be null/i",
            '/NOT NULL constraint failed: (?:\w+\.)?(?<col>\w+)/i',
        ], 'col')) {
            return __('The ":field" field is required — please fill it in and try again.', ['field' => self::humanize($column)]);
        }

        if (self::matches($message, [
            "/Duplicate entry '.*?' for key/i",
            '/UNIQUE constraint failed/i',
        ])) {
            return __('That already exists — please use a different value and try again.');
        }

        if (self::matches($message, [
            '/a foreign key constraint fails/i',
            '/FOREIGN KEY constraint failed/i',
        ])) {
            return __('This can\'t be saved because it refers to something that no longer exists, or is still linked to other records. Please refresh the page and try again.');
        }

        return null;
    }

    private static function matches(string $haystack, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack)) {
                return true;
            }
        }

        return false;
    }

    private static function match(string $haystack, array $patterns, string $group): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack, $m) && isset($m[$group])) {
                return $m[$group];
            }
        }

        return null;
    }

    private static function humanize(string $column): string
    {
        return Str::headline(Str::afterLast($column, '.'));
    }
}
