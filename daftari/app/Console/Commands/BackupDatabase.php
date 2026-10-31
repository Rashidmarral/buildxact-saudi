<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Support\DatabaseDumpTool;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Audit finding LOW-31: nothing in the app ever backed up the database —
 * a lost or corrupted server meant every tenant's data was simply gone.
 * Dumps the configured connection (mysqldump for mysql, pg_dump for
 * pgsql, a plain file copy for sqlite) gzip-compressed into the private
 * 'local' disk under backups/, then prunes anything older than the
 * configurable retention window. Scheduled daily in routes/console.php;
 * also runnable on demand from Admin > Backups.
 *
 * Go-live audit finding: the database dump alone left every uploaded
 * file (company logos/stamps, ZATCA certificates, invoice/bill
 * attachments, letterheads — all under storage/app/public when the
 * 'local' disk driver is active) with no backup at all. Also archives
 * that directory, unless the 'public' disk has been switched to S3
 * (see AppServiceProvider::configureStorageDriver) — at that point the
 * files no longer live on this server, and S3's own durability/
 * versioning is the operator's responsibility to configure, not this
 * command's.
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dump the database and public storage to storage/app/private/backups and prune old backups past the retention window';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d_His');
        $dbFilename = "backups/backup-{$connection}-{$timestamp}.sql.gz";

        try {
            $sql = match ($connection) {
                'mysql' => $this->dumpMysql($config),
                'pgsql' => $this->dumpPgsql($config),
                'sqlite' => $this->dumpSqlite($config),
                default => throw new \RuntimeException("Unsupported database connection for backup: {$connection}"),
            };

            Storage::disk('local')->put($dbFilename, gzencode($sql, 9));

            $sizeKb = round(Storage::disk('local')->size($dbFilename) / 1024, 1);
            $this->info("Database backup created: {$dbFilename} ({$sizeKb} KB)");

            $storageNote = $this->backupPublicStorage($timestamp);

            Setting::set('backup_last_run_at', now()->toDateTimeString());
            Setting::set('backup_last_status', 'success');
            Setting::set('backup_last_error', null);

            $this->prune();

            if ($storageNote) {
                $this->info($storageNote);
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Setting::set('backup_last_run_at', now()->toDateTimeString());
            Setting::set('backup_last_status', 'failed');
            Setting::set('backup_last_error', $e->getMessage());

            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * Returns a status line for the CLI, or null when there's nothing
     * local to archive (the 'public' disk is on S3).
     */
    private function backupPublicStorage(string $timestamp): ?string
    {
        if (config('filesystems.disks.public.driver') !== 'local') {
            return 'Public storage is on S3 — skipped (configure bucket versioning/lifecycle rules there instead).';
        }

        $root = config('filesystems.disks.public.root');

        if (! $root || ! is_dir($root)) {
            return null;
        }

        $filename = "backups/storage-{$timestamp}.tar.gz";
        $absolutePath = Storage::disk('local')->path($filename);

        $result = Process::run(['tar', '-czf', $absolutePath, '-C', $root, '.']);

        if (! $result->successful()) {
            throw new \RuntimeException('Public storage archive failed: '.$result->errorOutput());
        }

        $sizeKb = round(Storage::disk('local')->size($filename) / 1024, 1);

        return "Storage backup created: {$filename} ({$sizeKb} KB)";
    }

    /**
     * The binary path is configurable (Admin > Backups) — see
     * App\Support\DatabaseDumpTool — for servers where mysqldump exists
     * but isn't on PATH (common on Windows/XAMPP/WAMP installs).
     */
    private function dumpMysql(array $config): string
    {
        $binary = DatabaseDumpTool::mysqldumpPath();

        $args = [
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--user='.$config['username'],
            '--single-transaction',
            '--skip-lock-tables',
            '--no-tablespaces',
            $config['database'],
        ];

        $result = Process::env(['MYSQL_PWD' => $config['password']])->run($args);

        if (! $result->successful()) {
            throw new \RuntimeException(DatabaseDumpTool::failureMessage($binary, 'mysqldump', $result->errorOutput()));
        }

        return $result->output();
    }

    private function dumpPgsql(array $config): string
    {
        $binary = DatabaseDumpTool::pgDumpPath();

        $args = [
            $binary,
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--no-password',
            $config['database'],
        ];

        $result = Process::env(['PGPASSWORD' => $config['password']])->run($args);

        if (! $result->successful()) {
            throw new \RuntimeException(DatabaseDumpTool::failureMessage($binary, 'pg_dump', $result->errorOutput()));
        }

        return $result->output();
    }

    /**
     * sqlite has no dump utility worth shelling out to here — the whole
     * database is a single file, so its own bytes are the backup.
     */
    private function dumpSqlite(array $config): string
    {
        $path = $config['database'];

        if (! is_file($path)) {
            throw new \RuntimeException("SQLite database file not found: {$path}");
        }

        return file_get_contents($path);
    }

    private function prune(): void
    {
        $retentionDays = (int) Setting::get('backup_retention_days', 14);
        $cutoff = now()->subDays($retentionDays);

        foreach (Storage::disk('local')->files('backups') as $file) {
            $modified = Storage::disk('local')->lastModified($file);

            if ($modified && \Illuminate\Support\Carbon::createFromTimestamp($modified)->lt($cutoff)) {
                Storage::disk('local')->delete($file);
            }
        }
    }
}
