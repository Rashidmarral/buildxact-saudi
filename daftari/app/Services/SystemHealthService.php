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
            $oldestPendingMinutes = $oldestPendingAge ? now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($oldestPendingAge)) : 0;

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

        $minutesAgo = now()->diffInMinutes(\Carbon\Carbon::parse($heartbeat));

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
     * No backup mechanism exists in this application yet — reported
     * honestly as "not configured" rather than a fabricated timestamp.
     */
    private function backup(): array
    {
        return $this->result('backup', __('Last backup'), self::STATUS_WARNING, __('No backup system is configured for this installation.'));
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
