<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Bug report: mysqldump failed on a Windows server with "'mysqldump' is
 * not recognized as an internal or external command" — MySQL was
 * installed, but its bin folder wasn't on that server's PATH, which
 * Windows install steps commonly skip and Linux distro packages almost
 * always set for you. The binary path is now a setting (Admin > Backups)
 * defaulting to the bare command so an already-working install's
 * behavior never changes.
 *
 * Kept as a small, stateless class — rather than inline in
 * BackupDatabase — so its logic is testable without needing a real (or
 * fake) database connection under a swapped 'database.default'.
 */
class DatabaseDumpTool
{
    public static function mysqldumpPath(): string
    {
        return Setting::get('backup_mysqldump_path', 'mysqldump');
    }

    public static function pgDumpPath(): string
    {
        return Setting::get('backup_pgdump_path', 'pg_dump');
    }

    /**
     * Turns the raw OS error (which reads as a code bug to anyone who
     * doesn't already know what a PATH is) into an actionable message
     * pointing at the one setting that fixes it, whenever the failure
     * looks like "the binary itself couldn't be found or run" rather
     * than a real database error (wrong credentials, connection
     * refused, etc.) — that distinction only matters for how the hint
     * is worded, the underlying error text is always included either way.
     */
    public static function failureMessage(string $binary, string $label, string $errorOutput): string
    {
        $looksMissing = str_contains($errorOutput, 'is not recognized')
            || str_contains($errorOutput, 'No such file or directory')
            || str_contains($errorOutput, 'command not found');

        if (! $looksMissing) {
            return "{$label} failed: {$errorOutput}";
        }

        return "{$label} failed: {$errorOutput} — \"{$binary}\" isn't on this server's PATH. Set the full path to it "
            .'(e.g. "C:\\xampp\\mysql\\bin\\mysqldump.exe" on Windows, or "/usr/bin/mysqldump" on Linux) in Admin > Backups.';
    }
}
