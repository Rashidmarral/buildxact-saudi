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
     * doesn't already know what a PATH is, or what a Winsock provider
     * is) into an actionable message pointing at a real fix, whenever
     * the failure matches one of a few well-known patterns rather than
     * a genuine database error (wrong credentials, unknown database,
     * etc.) — that distinction only changes which hint gets appended,
     * the underlying error text is always included either way.
     */
    public static function failureMessage(string $binary, string $label, string $errorOutput): string
    {
        if (str_contains($errorOutput, 'is not recognized')
            || str_contains($errorOutput, 'No such file or directory')
            || str_contains($errorOutput, 'command not found')) {
            return "{$label} failed: {$errorOutput} — \"{$binary}\" isn't on this server's PATH. Set the full path to it "
                .'(e.g. "C:\\xampp\\mysql\\bin\\mysqldump.exe" on Windows, or "/usr/bin/mysqldump" on Linux) in Admin > Backups.';
        }

        // Windows-only: error 2004 / WSAEPROVIDERFAILEDINIT (socket code
        // 10106) means the OS's own Winsock network stack failed to
        // initialize for the process running mysqldump — the binary was
        // found and ran, it just couldn't open a network socket at all.
        // This is a Windows server config problem, not anything in this
        // app or its database credentials; the standard fix is resetting
        // the Winsock catalog and rebooting.
        if (str_contains($errorOutput, "Can't create TCP/IP socket")
            || str_contains($errorOutput, '(10106)')
            || str_contains($errorOutput, 'error: 2004')) {
            return "{$label} failed: {$errorOutput} — this is Windows' own network stack (Winsock) failing to "
                .'initialize for this process, not a wrong password or a database that\'s down. On the server, as '
                .'Administrator: run "netsh winsock reset catalog" in an elevated Command Prompt, then reboot the '
                .'server — that fixes this in the vast majority of cases. If it recurs, check for antivirus/firewall/VPN '
                .'software that installs its own network filter (it can re-corrupt the Winsock catalog after every reboot).';
        }

        return "{$label} failed: {$errorOutput}";
    }
}
