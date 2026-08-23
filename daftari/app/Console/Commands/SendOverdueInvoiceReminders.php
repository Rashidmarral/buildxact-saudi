<?php

namespace App\Console\Commands;

use App\Mail\OverdueInvoiceReminderMail;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendOverdueInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-overdue-reminders';

    protected $description = 'Email clients a reminder for invoices that are past their due date, throttled to once every 3 days per invoice';

    public function handle(): int
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->whereIn('status', ['sent', 'partially_paid'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->where(function ($query) {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<=', now()->subDays(3));
            })
            ->with('client', 'company')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->isOverdue() && $invoice->client?->email);

        foreach ($invoices as $invoice) {
            Mail::to($invoice->client->email)->send(new OverdueInvoiceReminderMail($invoice));
            $invoice->update(['last_reminder_sent_at' => now()]);
        }

        $this->info("Sent {$invoices->count()} overdue invoice reminder(s).");

        return self::SUCCESS;
    }
}
