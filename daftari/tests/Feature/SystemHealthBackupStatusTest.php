<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding OPS-03: SystemHealthService::backup() unconditionally
 * reported "no backup system is configured," even though a real one
 * (BackupDatabase / Admin\BackupController, both already writing the
 * backup_last_run_at/backup_last_status settings this check now reads)
 * exists in this codebase. A failed or stalled backup schedule looked
 * identical to a healthy one on the one dashboard meant to surface it.
 */
class SystemHealthBackupStatusTest extends TestCase
{
    use RefreshDatabase;

    private function backupCheck(): array
    {
        return collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'backup');
    }

    public function test_a_backup_that_has_never_run_is_reported_as_a_warning(): void
    {
        $check = $this->backupCheck();

        $this->assertSame(SystemHealthService::STATUS_WARNING, $check['status']);
        $this->assertStringContainsString('ever run', $check['detail']);
    }

    public function test_a_recent_successful_backup_is_reported_as_healthy(): void
    {
        Setting::set('backup_last_run_at', now()->subHours(2)->toDateTimeString());
        Setting::set('backup_last_status', 'success');

        $check = $this->backupCheck();

        $this->assertSame(SystemHealthService::STATUS_HEALTHY, $check['status']);
    }

    public function test_a_failed_backup_run_is_reported_as_failed(): void
    {
        Setting::set('backup_last_run_at', now()->subHours(2)->toDateTimeString());
        Setting::set('backup_last_status', 'failed');

        $check = $this->backupCheck();

        $this->assertSame(SystemHealthService::STATUS_FAILED, $check['status']);
    }

    public function test_a_stale_backup_schedule_is_reported_as_a_warning_then_failed(): void
    {
        Setting::set('backup_last_run_at', now()->subHours(48)->toDateTimeString());
        Setting::set('backup_last_status', 'success');

        $warningCheck = $this->backupCheck();
        $this->assertSame(SystemHealthService::STATUS_WARNING, $warningCheck['status']);

        Setting::set('backup_last_run_at', now()->subDays(5)->toDateTimeString());

        $failedCheck = $this->backupCheck();
        $this->assertSame(SystemHealthService::STATUS_FAILED, $failedCheck['status']);
    }

    /**
     * Found alongside OPS-03, same root cause: diffInMinutes()/
     * diffInHours() against a past timestamp returned a negative number
     * in this installed Carbon version, which made every "<= N minutes"
     * threshold trivially true regardless of how stale the heartbeat
     * actually was — the scheduler check silently always reported
     * healthy.
     */
    public function test_a_stale_scheduler_heartbeat_is_reported_as_failed_not_healthy(): void
    {
        Setting::set('system_scheduler_heartbeat_at', now()->subHours(2)->toDateTimeString());

        $check = collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'scheduler');

        $this->assertSame(SystemHealthService::STATUS_FAILED, $check['status']);
    }

    public function test_a_fresh_scheduler_heartbeat_is_reported_as_healthy(): void
    {
        Setting::set('system_scheduler_heartbeat_at', now()->subMinute()->toDateTimeString());

        $check = collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'scheduler');

        $this->assertSame(SystemHealthService::STATUS_HEALTHY, $check['status']);
    }
}
