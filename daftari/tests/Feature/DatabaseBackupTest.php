<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Audit finding LOW-31: nothing in the app ever backed up the database.
 * The backup:run command dumps the active connection (a plain file copy
 * for sqlite, since it has no separate dump utility worth shelling out
 * to) gzip-compressed to the private 'local' disk under backups/, records
 * the outcome in Setting, and prunes anything past the retention window.
 * Admin > Backups lists what it has produced and lets a super admin
 * trigger one on demand, adjust retention, or download/delete a dump.
 */
class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        foreach (Storage::disk('local')->files('backups') as $file) {
            Storage::disk('local')->delete($file);
        }

        parent::tearDown();
    }

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    /**
     * The test database connection itself is sqlite ':memory:' (no file
     * on disk to copy), so dumpSqlite() would have nothing to read —
     * these tests only exercise the backup mechanism (dump → gzip → store
     * → prune), not that it captures the live test database, so pointing
     * the sqlite connection config at a real throwaway file is enough.
     */
    private function useFileBasedSqliteConnection(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'backup-test-').'.sqlite';
        file_put_contents($path, 'SQLite format 3'.str_repeat("\0", 100));
        config(['database.connections.sqlite.database' => $path]);
    }

    public function test_backup_run_creates_a_gzipped_dump_and_records_success(): void
    {
        $this->useFileBasedSqliteConnection();

        $this->artisan('backup:run')->assertExitCode(0);

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(2, $files);
        $this->assertCount(1, array_filter($files, fn ($f) => str_ends_with($f, '.sql.gz')));
        $this->assertCount(1, array_filter($files, fn ($f) => str_ends_with($f, '.tar.gz')));
        $this->assertSame('success', Setting::get('backup_last_status'));
        $this->assertNotNull(Setting::get('backup_last_run_at'));
    }

    public function test_backup_run_also_archives_uploaded_files_from_the_public_disk(): void
    {
        $this->useFileBasedSqliteConnection();
        Storage::disk('public')->put('logos/test-logo.png', 'fake-logo-bytes');

        $this->artisan('backup:run')->assertExitCode(0);

        $archives = array_values(array_filter(
            Storage::disk('local')->files('backups'),
            fn ($f) => str_ends_with($f, '.tar.gz')
        ));
        $this->assertCount(1, $archives);
        $this->assertGreaterThan(0, Storage::disk('local')->size($archives[0]));

        Storage::disk('public')->delete('logos/test-logo.png');
    }

    public function test_backup_run_skips_the_storage_archive_when_the_public_disk_is_s3(): void
    {
        $this->useFileBasedSqliteConnection();
        config(['filesystems.disks.public.driver' => 's3']);

        $this->artisan('backup:run')->assertExitCode(0);

        $files = Storage::disk('local')->files('backups');
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sql.gz', $files[0]);
    }

    public function test_backup_run_prunes_files_older_than_the_retention_window(): void
    {
        $this->useFileBasedSqliteConnection();
        Setting::set('backup_retention_days', 7);
        Storage::disk('local')->put('backups/old-backup.sql.gz', 'stale');
        touch(Storage::disk('local')->path('backups/old-backup.sql.gz'), now()->subDays(10)->timestamp);

        Artisan::call('backup:run');

        $this->assertFalse(Storage::disk('local')->exists('backups/old-backup.sql.gz'));
    }

    public function test_the_admin_backups_page_lists_existing_dumps(): void
    {
        $admin = $this->makeSuperAdmin();
        Storage::disk('local')->put('backups/backup-sqlite-2026-01-01_000000.sql.gz', 'dump');

        $response = $this->actingAs($admin)->get(route('admin.backups.index'));

        $response->assertOk();
        $response->assertSee('backup-sqlite-2026-01-01_000000.sql.gz');
    }

    public function test_a_super_admin_can_trigger_a_backup_on_demand(): void
    {
        $this->useFileBasedSqliteConnection();
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->post(route('admin.backups.run'));

        $response->assertRedirect();
        $this->assertNotEmpty(Storage::disk('local')->files('backups'));
    }

    public function test_downloading_a_backup_requires_a_confirmed_password(): void
    {
        $admin = $this->makeSuperAdmin();
        Storage::disk('local')->put('backups/backup-sqlite-2026-01-01_000000.sql.gz', 'dump');

        $response = $this->actingAs($admin)->get(route('admin.backups.download', 'backup-sqlite-2026-01-01_000000.sql.gz'));

        $response->assertRedirect(route('admin.password.confirm'));
    }

    public function test_downloading_a_backup_with_a_confirmed_password_streams_the_file(): void
    {
        $admin = $this->makeSuperAdmin();
        Storage::disk('local')->put('backups/backup-sqlite-2026-01-01_000000.sql.gz', 'dump');

        $response = $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('admin.backups.download', 'backup-sqlite-2026-01-01_000000.sql.gz'));

        $response->assertOk();
    }

    public function test_a_path_traversal_attempt_in_the_filename_is_rejected(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->get(route('admin.backups.download', '..%2F..%2F.env'));

        $response->assertNotFound();
    }

    // ---------------------------------------------------------------
    // Configurable dump-tool path (App\Support\DatabaseDumpTool) — bug
    // report: mysqldump failed on a Windows server with "'mysqldump' is
    // not recognized as an internal or external command" because
    // MySQL's bin folder wasn't on that server's PATH, even though the
    // binary itself was installed. Tested directly against the small
    // stateless helper class rather than through the full backup:run
    // command, which would otherwise need a real/faked non-sqlite
    // connection under a swapped 'database.default' — not worth the
    // transaction-handling complexity that adds to every other test in
    // this RefreshDatabase-backed class.
    // ---------------------------------------------------------------

    public function test_dump_tool_path_defaults_to_the_bare_command_name(): void
    {
        $this->assertSame('mysqldump', \App\Support\DatabaseDumpTool::mysqldumpPath());
        $this->assertSame('pg_dump', \App\Support\DatabaseDumpTool::pgDumpPath());
    }

    public function test_dump_tool_path_reflects_a_saved_setting(): void
    {
        Setting::set('backup_mysqldump_path', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');

        $this->assertSame('C:\\xampp\\mysql\\bin\\mysqldump.exe', \App\Support\DatabaseDumpTool::mysqldumpPath());
    }

    public function test_a_missing_binary_failure_gets_a_helpful_path_hint(): void
    {
        $message = \App\Support\DatabaseDumpTool::failureMessage(
            'mysqldump',
            'mysqldump',
            "'mysqldump' is not recognized as an internal or external command, operable program or batch file.",
        );

        $this->assertStringContainsString("isn't on this server's PATH", $message);
        $this->assertStringContainsString('Admin > Backups', $message);
    }

    public function test_a_missing_binary_failure_on_linux_also_gets_the_hint(): void
    {
        $message = \App\Support\DatabaseDumpTool::failureMessage(
            'mysqldump',
            'mysqldump',
            'sh: 1: mysqldump: command not found',
        );

        $this->assertStringContainsString("isn't on this server's PATH", $message);
    }

    public function test_a_real_database_error_does_not_get_the_path_hint(): void
    {
        $message = \App\Support\DatabaseDumpTool::failureMessage(
            'mysqldump',
            'mysqldump',
            'mysqldump: Got error: 1045: Access denied for user',
        );

        $this->assertStringNotContainsString('PATH', $message);
        $this->assertStringContainsString('Access denied', $message);
    }

    public function test_an_admin_can_save_a_custom_mysqldump_path(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->post(route('admin.backups.dump-paths'), [
            'mysqldump_path' => 'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        ]);

        $response->assertRedirect();
        $this->assertSame('C:\\xampp\\mysql\\bin\\mysqldump.exe', Setting::get('backup_mysqldump_path'));
        $this->assertSame('pg_dump', Setting::get('backup_pgdump_path'));
    }

    public function test_leaving_the_dump_path_blank_resets_it_to_the_bare_command(): void
    {
        $admin = $this->makeSuperAdmin();
        Setting::set('backup_mysqldump_path', 'C:\\xampp\\mysql\\bin\\mysqldump.exe');

        $this->actingAs($admin)->post(route('admin.backups.dump-paths'), ['mysqldump_path' => '']);

        $this->assertSame('mysqldump', Setting::get('backup_mysqldump_path'));
    }
}
