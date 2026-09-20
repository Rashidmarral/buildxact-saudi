<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Touches a timestamp every minute the scheduler actually runs — the only
// way the Super Admin "System Health" card can tell "the server's cron
// entry is invoking schedule:run" apart from "it was never configured".
Schedule::call(fn () => Setting::set('system_scheduler_heartbeat_at', now()->toDateTimeString()))->everyMinute();

// Security audit finding M-10: only backup:run used withoutOverlapping().
// Every command below either creates records (recurring invoices/
// expenses/journal entries, ZATCA submissions) or mutates state
// (subscription lifecycle, depreciation postings) — an overrunning
// invocation still on the same schedule's next tick would otherwise
// duplicate whatever it creates or posts, not just waste work.
Schedule::command('zatca:sync-invoices --frequency=hourly')->hourly()->withoutOverlapping();
Schedule::command('zatca:sync-invoices --frequency=daily')->daily()->withoutOverlapping();
Schedule::command('zatca:sync-invoices --frequency=weekly')->weekly()->withoutOverlapping();

Schedule::command('invoices:send-overdue-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('quotations:expire')->dailyAt('00:15')->withoutOverlapping();
Schedule::command('invoices:generate-recurring')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('expenses:generate-recurring')->dailyAt('06:15')->withoutOverlapping();
Schedule::command('journals:generate-recurring')->dailyAt('06:20')->withoutOverlapping();
Schedule::command('subscriptions:send-expiring-reminders')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('subscriptions:expire-cancelled')->dailyAt('01:00')->withoutOverlapping();
Schedule::command('subscriptions:run-lifecycle-rules')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('assets:run-depreciation')->monthlyOn(1, '02:00')->withoutOverlapping();
Schedule::command('inventory:check-low-stock')->dailyAt('07:00')->withoutOverlapping();
Schedule::command('backup:run')->dailyAt('03:00')->withoutOverlapping();
