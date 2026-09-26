<?php

namespace App\Console\Commands;

use App\Mail\OverdueInvoiceReminderMail;
use App\Models\Invoice;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Audit finding MEDIUM-13: overdue-invoice reminders used to be a single,
 * flat "friendly reminder" repeated every 3 days for as long as an invoice
 * stayed unpaid — the same gentle tone on day 8 as on day 90. Real dunning
 * escalates: a soft nudge once it's genuinely late, a firmer notice once
 * it's been ignored, and a final notice once it's seriously overdue. This
 * now walks a 3-rung ladder (7 / 14 / 30 days overdue) and only escalates
 * to a rung once — invoices/last_reminder_tier remembers the highest rung
 * already sent so re-running the command never re-sends the same tone.
 */
class SendOverdueInvoiceReminders extends Command
{
    protected $signature = 'invoices:send-overdue-reminders';

    protected $description = 'Email clients an escalating reminder (7/14/30 days overdue) as their invoice payment falls further behind';

    /** Days overdue required to reach each ladder tier. */
    private const LADDER = [
        3 => 30,
        2 => 14,
        1 => 7,
    ];

    public function handle(): int
    {
        $invoices = Invoice::withoutGlobalScopes()
            ->whereIn('status', ['sent', 'partially_paid'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now()->toDateString())
            ->with('client', 'company')
            ->get()
            ->filter(fn (Invoice $invoice) => $invoice->isOverdue()
                && $invoice->client?->email
                && $invoice->company?->invoice_dunning_enabled !== false);

        $sent = 0;

        foreach ($invoices as $invoice) {
            $tier = $this->tierFor($invoice->daysOverdue());

            if ($tier === null || $tier <= (int) $invoice->last_reminder_tier) {
                continue;
            }

            Mail::to($invoice->client->email)->send(new OverdueInvoiceReminderMail($invoice, $tier));
            $invoice->update(['last_reminder_sent_at' => now(), 'last_reminder_tier' => $tier]);
            $sent++;
        }

        $this->info("Sent {$sent} overdue invoice reminder(s).");

        return self::SUCCESS;
    }

    private function tierFor(int $daysOverdue): ?int
    {
        foreach (self::LADDER as $tier => $threshold) {
            if ($daysOverdue >= $threshold) {
                return $tier;
            }
        }

        return null;
    }
}
