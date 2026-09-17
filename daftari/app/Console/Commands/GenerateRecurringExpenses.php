<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use Illuminate\Console\Command;

class GenerateRecurringExpenses extends Command
{
    protected $signature = 'expenses:generate-recurring';

    protected $description = 'Generate an expense for every active recurring expense whose next run date has arrived';

    public function handle(): int
    {
        $due = RecurringExpense::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->with('company')
            ->get()
            // Skip a suspended or subscription-lapsed company entirely
            // (security audit finding D-0) — see the matching comment in
            // GenerateRecurringInvoices for why.
            ->filter(fn (RecurringExpense $recurringExpense) => $recurringExpense->company
                && $recurringExpense->company->isOperational());

        foreach ($due as $recurringExpense) {
            $recurringExpense->generateExpense();
        }

        $this->info("Generated {$due->count()} expense(s) from recurring expenses.");

        return self::SUCCESS;
    }
}
