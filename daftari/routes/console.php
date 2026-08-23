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
