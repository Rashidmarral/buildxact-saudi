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
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.sql.gz', $files[0]);
        $this->assertSame('success', Setting::get('backup_last_status'));
        $this->assertNotNull(Setting::get('backup_last_run_at'));
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
}
