<?php

namespace App\Console\Commands;

use App\Models\Setting;
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
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Dump the database to storage/app/private/backups and prune old backups past the retention window';

    public function handle(): int
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");
        $timestamp = now()->format('Y-m-d_His');
        $filename = "backups/backup-{$connection}-{$timestamp}.sql.gz";

        try {
            $sql = match ($connection) {
                'mysql' => $this->dumpMysql($config),
                'pgsql' => $this->dumpPgsql($config),
                'sqlite' => $this->dumpSqlite($config),
                default => throw new \RuntimeException("Unsupported database connection for backup: {$connection}"),
            };

            Storage::disk('local')->put($filename, gzencode($sql, 9));

            $sizeKb = round(Storage::disk('local')->size($filename) / 1024, 1);
            Setting::set('backup_last_run_at', now()->toDateTimeString());
            Setting::set('backup_last_status', 'success');
            Setting::set('backup_last_error', null);

            $this->prune();

            $this->info("Backup created: {$filename} ({$sizeKb} KB)");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            Setting::set('backup_last_run_at', now()->toDateTimeString());
            Setting::set('backup_last_status', 'failed');
            Setting::set('backup_last_error', $e->getMessage());

            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function dumpMysql(array $config): string
    {
        $args = [
            'mysqldump',
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
            throw new \RuntimeException('mysqldump failed: '.$result->errorOutput());
        }

        return $result->output();
    }

    private function dumpPgsql(array $config): string
    {
        $args = [
            'pg_dump',
            '--host='.$config['host'],
            '--port='.$config['port'],
            '--username='.$config['username'],
            '--no-password',
            $config['database'],
        ];

        $result = Process::env(['PGPASSWORD' => $config['password']])->run($args);

        if (! $result->successful()) {
            throw new \RuntimeException('pg_dump failed: '.$result->errorOutput());
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
