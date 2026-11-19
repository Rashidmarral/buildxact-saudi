<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding CRIT-01: `php artisan storage:link` created a real
 * public/storage symlink to storage/app/public, which the web server would
 * serve directly — bypassing FileServeController's auth + tenant check
 * entirely for every invoice/bill/PO/quotation attachment and company legal
 * document. Fixed by emptying config/filesystems.php's 'links' array (so
 * storage:link is a no-op) and no longer calling it from the installer.
 */
class PublicStorageSymlinkRemovedTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_symlink_configuration_is_empty(): void
    {
        $this->assertSame([], config('filesystems.links'), 'config/filesystems.php must not define a public/storage symlink target.');
    }

    public function test_running_storage_link_creates_no_symlink(): void
    {
        $symlinkPath = public_path('storage');

        if (is_link($symlinkPath)) {
            @unlink($symlinkPath);
        }

        $this->artisan('storage:link');

        $this->assertFalse(is_link($symlinkPath), 'storage:link must not (re)create the public/storage symlink.');
    }

    public function test_the_cleanup_migration_removes_a_pre_existing_symlink(): void
    {
        $symlinkPath = public_path('storage');

        if (is_link($symlinkPath) || file_exists($symlinkPath)) {
            @unlink($symlinkPath);
        }
        symlink(storage_path('app/public'), $symlinkPath);
        $this->assertTrue(is_link($symlinkPath));

        $migration = require database_path('migrations/2026_09_17_030000_remove_dangerous_public_storage_symlink.php');
        $migration->up();

        $this->assertFalse(is_link($symlinkPath), 'The migration must remove a symlink left over from an older installer run.');
    }

    public function test_no_view_or_route_references_the_symlinked_storage_url_scheme(): void
    {
        $publicDiskUrl = \Illuminate\Support\Facades\Storage::disk('public')->url('example.jpg');

        $this->assertStringNotContainsString('/storage/', $publicDiskUrl, 'Public disk URLs must be built through /files/, not the removed /storage/ symlink path.');
        $this->assertStringContainsString('/files/', $publicDiskUrl);
    }
}
