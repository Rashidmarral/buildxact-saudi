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

Schedule::command('zatca:sync-invoices --frequency=hourly')->hourly();
Schedule::command('zatca:sync-invoices --frequency=daily')->daily();
Schedule::command('zatca:sync-invoices --frequency=weekly')->weekly();

Schedule::command('invoices:send-overdue-reminders')->dailyAt('08:00');
Schedule::command('invoices:generate-recurring')->dailyAt('06:00');
Schedule::command('expenses:generate-recurring')->dailyAt('06:15');
Schedule::command('subscriptions:send-expiring-reminders')->dailyAt('07:00');
Schedule::command('subscriptions:expire-cancelled')->dailyAt('01:00');
Schedule::command('assets:run-depreciation')->monthlyOn(1, '02:00');
Schedule::command('inventory:check-low-stock')->dailyAt('07:00');
