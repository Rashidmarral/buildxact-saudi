<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Audit finding LOW-31: lists what backup:run has produced, lets a super
 * admin trigger one on demand and download or delete individual dumps,
 * and configure how many days of backups are kept.
 */
class BackupController extends Controller
{
    public function index()
    {
        $files = collect(Storage::disk('local')->files('backups'))
            ->sortByDesc(fn (string $file) => Storage::disk('local')->lastModified($file))
            ->map(fn (string $file) => [
                'name' => basename($file),
                'path' => $file,
                'size' => Storage::disk('local')->size($file),
                'modified_at' => Storage::disk('local')->lastModified($file),
            ])
            ->values();

        return view('admin.backups.index', [
            'files' => $files,
            'retentionDays' => (int) Setting::get('backup_retention_days', 14),
            'lastRunAt' => Setting::get('backup_last_run_at'),
            'lastStatus' => Setting::get('backup_last_status'),
            'lastError' => Setting::get('backup_last_error'),
            'connection' => config('database.default'),
            'mysqldumpPath' => Setting::get('backup_mysqldump_path', 'mysqldump'),
            'pgDumpPath' => Setting::get('backup_pgdump_path', 'pg_dump'),
        ]);
    }

    public function runNow()
    {
        Artisan::call('backup:run');
        $output = trim(Artisan::output());

        AuditLog::record('backups.run', null, __('Ran a database backup on demand'));

        return back()->with('status', $output !== '' ? $output : __('Backup completed.'));
    }

    public function updateRetention(Request $request)
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        Setting::set('backup_retention_days', $data['retention_days']);

        AuditLog::record('backups.update_retention', null, __('Set backup retention to :days day(s)', ['days' => $data['retention_days']]));

        return back()->with('status', __('Backup retention updated.'));
    }

    /**
     * Bug report: mysqldump failing with "'mysqldump' is not recognized"
     * on a Windows server where MySQL is installed but its bin folder
     * isn't on PATH. Lets an operator point straight at the binary
     * instead of needing shell/PATH access on their own server.
     */
    public function updateDumpPaths(Request $request)
    {
        $data = $request->validate([
            'mysqldump_path' => ['nullable', 'string', 'max:255'],
            'pgdump_path' => ['nullable', 'string', 'max:255'],
        ]);

        Setting::set('backup_mysqldump_path', filled($data['mysqldump_path'] ?? null) ? $data['mysqldump_path'] : 'mysqldump');
        Setting::set('backup_pgdump_path', filled($data['pgdump_path'] ?? null) ? $data['pgdump_path'] : 'pg_dump');

        AuditLog::record('backups.update_dump_paths', null, __('Updated the database dump tool path(s)'));

        return back()->with('status', __('Dump tool path saved.'));
    }

    public function download(Request $request, string $filename)
    {
        $path = $this->validatedPath($filename);

        return Storage::disk('local')->download($path);
    }

    public function destroy(string $filename)
    {
        $path = $this->validatedPath($filename);

        Storage::disk('local')->delete($path);

        AuditLog::record('backups.delete', null, __('Deleted backup file :file', ['file' => $filename]));

        return back()->with('status', __('Backup deleted.'));
    }

    /**
     * $filename arrives from the URL as a bare basename — basename()
     * strips any directory components a request tried to smuggle in
     * (e.g. "../../.env"), so the resolved path can never leave backups/.
     */
    private function validatedPath(string $filename): string
    {
        $path = 'backups/'.basename($filename);

        abort_unless(Storage::disk('local')->exists($path), 404);

        return $path;
    }
}
