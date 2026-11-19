<?php

namespace App\Services;

use App\Models\PaymentGateway;
use App\Models\Setting;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Real, cheap health signals for the Super Admin "System Health" card —
 * every check either hits infrastructure directly (DB/cache round-trip,
 * disk_free_space()) or reads data the app already produces (failed_jobs,
 * ZATCA sync logs, the scheduler heartbeat below). Nothing here is a
 * placeholder/static value.
 *
 * Each check returns one of three statuses: 'healthy', 'warning', 'failed'.
 */
class SystemHealthService
{
    public const STATUS_HEALTHY = 'healthy';

    public const STATUS_WARNING = 'warning';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<int, array{key: string, label: string, status: string, detail: string}>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->cache(),
            $this->queue(),
            $this->scheduler(),
            $this->storage(),
            $this->email(),
            $this->paymentGateway(),
            $this->zatca(),
            $this->backup(),
        ];
    }

    private function database(): array
    {
        try {
            DB::connection()->getPdo();
            DB::select('select 1');

            return $this->result('database', __('Database'), self::STATUS_HEALTHY, __('Connected.'));
        } catch (\Throwable $e) {
            return $this->result('database', __('Database'), self::STATUS_FAILED, $e->getMessage());
        }
    }

    private function cache(): array
    {
        try {
            $key = 'health-check:'.now()->timestamp;
            Cache::put($key, true, 5);
            $ok = Cache::get($key) === true;
            Cache::forget($key);

            return $ok
                ? $this->result('cache', __('Cache'), self::STATUS_HEALTHY, __('Read/write round-trip succeeded.'))
                : $this->result('cache', __('Cache'), self::STATUS_WARNING, __('Cache store did not return the written value.'));
        } catch (\Throwable $e) {
            return $this->result('cache', __('Cache'), self::STATUS_FAILED, $e->getMessage());
        }
    }

    private function queue(): array
    {
        try {
            $failedLast24h = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();

            $oldestPendingAge = DB::table('jobs')
                ->whereNull('reserved_at')
                ->min('available_at');
            // Same sign bug as the scheduler check below (found
            // alongside OPS-03) — diffInMinutes() returns negative once
            // $oldestPendingAge is in the past, which silently defeated
            // both threshold checks a few lines down.
            $oldestPendingMinutes = $oldestPendingAge ? abs(now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($oldestPendingAge))) : 0;

            if ($failedLast24h >= 10 || $oldestPendingMinutes >= 60) {
                return $this->result('queue', __('Queue'), self::STATUS_FAILED, __(':count failed in the last 24h, oldest pending job :minutes min old.', ['count' => $failedLast24h, 'minutes' => $oldestPendingMinutes]));
            }

            if ($failedLast24h > 0 || $oldestPendingMinutes >= 15) {
                return $this->result('queue', __('Queue'), self::STATUS_WARNING, __(':count failed in the last 24h, oldest pending job :minutes min old.', ['count' => $failedLast24h, 'minutes' => $oldestPendingMinutes]));
            }

            return $this->result('queue', __('Queue'), self::STATUS_HEALTHY, __('No failed jobs in the last 24h.'));
        } catch (\Throwable $e) {
            return $this->result('queue', __('Queue'), self::STATUS_FAILED, $e->getMessage());
        }
    }

    /**
     * Relies on the heartbeat scheduled in routes/console.php
     * (Schedule::call(...)->everyMinute()) touching this setting — the
     * only way this app can tell "cron is actually invoking
     * schedule:run" from "cron was never set up on the server" apart.
     */
    private function scheduler(): array
    {
        $heartbeat = Setting::get('system_scheduler_heartbeat_at');

        if (! $heartbeat) {
            return $this->result('scheduler', __('Scheduler / Cron'), self::STATUS_WARNING, __('No heartbeat recorded yet — the server cron entry for schedule:run may not be configured.'));
        }

        // Found alongside OPS-03 (identical root cause): diffInMinutes()
        // here returns a NEGATIVE number once the heartbeat is actually
        // in the past (as it always is) — every negative number passes
        // "<= 3", so this reported "healthy" unconditionally regardless
        // of how long ago cron actually last ran, the one case this
        // check exists to catch.
        $minutesAgo = abs(now()->diffInMinutes(\Carbon\Carbon::parse($heartbeat)));

        return match (true) {
            $minutesAgo <= 3 => $this->result('scheduler', __('Scheduler / Cron'), self::STATUS_HEALTHY, __('Last heartbeat :minutes min ago.', ['minutes' => $minutesAgo])),
            $minutesAgo <= 30 => $this->result('scheduler', __('Scheduler / Cron'), self::STATUS_WARNING, __('Last heartbeat :minutes min ago.', ['minutes' => $minutesAgo])),
            default => $this->result('scheduler', __('Scheduler / Cron'), self::STATUS_FAILED, __('Last heartbeat :minutes min ago — cron may have stopped.', ['minutes' => $minutesAgo])),
        };
    }

    private function storage(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if (! $free || ! $total) {
            return $this->result('storage', __('Storage'), self::STATUS_WARNING, __('Could not read disk usage on this filesystem.'));
        }

        $freePercent = round(($free / $total) * 100, 1);

        return match (true) {
            $freePercent < 5 => $this->result('storage', __('Storage'), self::STATUS_FAILED, __(':percent% free disk space.', ['percent' => $freePercent])),
            $freePercent < 20 => $this->result('storage', __('Storage'), self::STATUS_WARNING, __(':percent% free disk space.', ['percent' => $freePercent])),
            default => $this->result('storage', __('Storage'), self::STATUS_HEALTHY, __(':percent% free disk space.', ['percent' => $freePercent])),
        };
    }

    private function email(): array
    {
        $driver = config('mail.default');

        if (in_array($driver, ['log', 'array'], true)) {
            return $this->result('email', __('Email'), self::STATUS_WARNING, __('Mail driver is ":driver" — emails are not actually being sent.', ['driver' => $driver]));
        }

        return $this->result('email', __('Email'), self::STATUS_HEALTHY, __('Mail driver ":driver" configured.', ['driver' => $driver]));
    }

    private function paymentGateway(): array
    {
        $enabled = PaymentGateway::whereNull('company_id')->where('is_enabled', true)->count();

        return $enabled > 0
            ? $this->result('payment_gateway', __('Payment gateway'), self::STATUS_HEALTHY, __(':count platform gateway(s) enabled.', ['count' => $enabled]))
            : $this->result('payment_gateway', __('Payment gateway'), self::STATUS_WARNING, __('No platform payment gateway is enabled.'));
    }

    private function zatca(): array
    {
        $failed = ZatcaInvoiceLog::withoutGlobalScopes()->where('status', 'failed')->where('submitted_at', '>=', now()->subDay())->count()
            + ZatcaCreditNoteLog::withoutGlobalScopes()->where('status', 'failed')->where('submitted_at', '>=', now()->subDay())->count();

        return match (true) {
            $failed >= 5 => $this->result('zatca', __('ZATCA integration'), self::STATUS_FAILED, __(':count failed submissions in the last 24h.', ['count' => $failed])),
            $failed > 0 => $this->result('zatca', __('ZATCA integration'), self::STATUS_WARNING, __(':count failed submissions in the last 24h.', ['count' => $failed])),
            default => $this->result('zatca', __('ZATCA integration'), self::STATUS_HEALTHY, __('No failed submissions in the last 24h.')),
        };
    }

    /**
     * Audit finding OPS-03: this unconditionally reported "no backup
     * system is configured," regardless of whether one actually was —
     * stale the moment BackupDatabase/BackupController shipped (see
     * Admin\BackupController::index(), which already reads these exact
     * same Setting keys to build its own "Last run" status). A failed
     * or silently-stalled backup run looked identical to a healthy one
     * on the one dashboard meant to surface it.
     */
    private function backup(): array
    {
        $lastRunAt = Setting::get('backup_last_run_at');

        if (! $lastRunAt) {
            return $this->result('backup', __('Last backup'), self::STATUS_WARNING, __('No backup has ever run on this installation.'));
        }

        $lastStatus = Setting::get('backup_last_status');
        $hoursAgo = abs(now()->diffInHours(\Carbon\Carbon::parse($lastRunAt)));

        if ($lastStatus === 'failed') {
            return $this->result('backup', __('Last backup'), self::STATUS_FAILED, __('The last backup run failed (:when).', ['when' => \Carbon\Carbon::parse($lastRunAt)->diffForHumans()]));
        }

        return match (true) {
            $hoursAgo <= 26 => $this->result('backup', __('Last backup'), self::STATUS_HEALTHY, __('Last backup succeeded :when.', ['when' => \Carbon\Carbon::parse($lastRunAt)->diffForHumans()])),
            $hoursAgo <= 72 => $this->result('backup', __('Last backup'), self::STATUS_WARNING, __('Last successful backup was :when — the schedule may have stopped running.', ['when' => \Carbon\Carbon::parse($lastRunAt)->diffForHumans()])),
            default => $this->result('backup', __('Last backup'), self::STATUS_FAILED, __('Last successful backup was :when — the schedule has likely stopped running.', ['when' => \Carbon\Carbon::parse($lastRunAt)->diffForHumans()])),
        };
    }

    private function result(string $key, string $label, string $status, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    /**
     * Count of ERROR/CRITICAL/ALERT/EMERGENCY log lines in the last 24h,
     * for the "System errors" Attention item. Only reads the tail of the
     * file (bounded read, not the whole log) so this stays cheap
     * regardless of how large laravel.log has grown.
     */
    public function recentErrorCount(int $tailBytes = 300000): int
    {
        $path = storage_path('logs/laravel.log');

        if (! is_readable($path)) {
            return 0;
        }

        $size = filesize($path);
        $handle = @fopen($path, 'r');

        if (! $handle) {
            return 0;
        }

        $offset = max(0, $size - $tailBytes);
        fseek($handle, $offset);
        $chunk = fread($handle, $tailBytes) ?: '';
        fclose($handle);

        $since = now()->subDay();
        $count = 0;

        foreach (preg_split('/\R/', $chunk) as $line) {
            if (! preg_match('/^\[(?<date>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\].*\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $line, $m)) {
                continue;
            }

            try {
                if (\Carbon\Carbon::parse($m['date'])->greaterThanOrEqualTo($since)) {
                    $count++;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $count;
    }
}
