<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('zatca:sync-invoices --frequency=hourly')->hourly();
Schedule::command('zatca:sync-invoices --frequency=daily')->daily();
Schedule::command('zatca:sync-invoices --frequency=weekly')->weekly();

Schedule::command('invoices:send-overdue-reminders')->dailyAt('08:00');
Schedule::command('invoices:generate-recurring')->dailyAt('06:00');
Schedule::command('subscriptions:send-expiring-reminders')->dailyAt('07:00');
Schedule::command('subscriptions:expire-cancelled')->dailyAt('01:00');
Schedule::command('assets:run-depreciation')->monthlyOn(1, '02:00');
