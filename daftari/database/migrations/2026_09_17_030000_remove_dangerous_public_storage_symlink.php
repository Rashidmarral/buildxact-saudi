<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Security audit finding CRIT-01: every file this app serves is routed
 * through App\Http\Controllers\FileServeController's /files/{filepath}
 * route, which enforces auth + tenant ownership. Older installer runs
 * (before this fix) also called `storage:link`, creating a real
 * public/storage symlink to storage/app/public — since the web server
 * serves static files by path existence, not by which route the app
 * intended, that symlink let anyone with a file's path download it
 * directly (invoice/bill/PO/quotation attachments, company legal
 * documents) with no login and no tenant check, completely bypassing
 * FileServeController.
 *
 * config/filesystems.php's 'links' array is now empty so a future
 * `storage:link` run is a no-op, but that doesn't undo a symlink an
 * earlier install already created — this migration removes it on
 * upgrade for every existing deployment.
 */
return new class extends Migration
{
    public function up(): void
    {
        $symlinkPath = public_path('storage');

        if (is_link($symlinkPath)) {
            @unlink($symlinkPath);
        }
    }

    public function down(): void
    {
        // Deliberately irreversible — recreating the symlink would
        // reintroduce the vulnerability this migration fixes.
    }
};
