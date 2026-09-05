<?php

namespace App\Console\Commands;

use App\Models\RecurringInvoice;
use Illuminate\Console\Command;

class GenerateRecurringInvoices extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate a draft invoice for every active recurring invoice whose next run date has arrived';

    public function handle(): int
    {
        $due = RecurringInvoice::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->with('items', 'company')
            // Skip companies that have downgraded off the recurring-invoices
            // feature — their schedule stays as-is and picks back up
            // automatically if they upgrade again.
            ->get()
            ->filter(fn (RecurringInvoice $recurringInvoice) => $recurringInvoice->company?->hasFeature('recurring_invoices'));

        foreach ($due as $recurringInvoice) {
            $recurringInvoice->generateInvoice();
        }

        $this->info("Generated {$due->count()} invoice(s) from recurring invoices.");

        return self::SUCCESS;
    }
}
