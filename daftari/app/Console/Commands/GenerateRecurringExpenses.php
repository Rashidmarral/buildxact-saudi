<?php

namespace App\Console\Commands;

use App\Models\RecurringExpense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

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

        $generated = 0;

        foreach ($due as $recurringExpense) {
            try {
                $recurringExpense->generateExpense();
                $generated++;
            } catch (Throwable $e) {
                // Security audit finding M-22: one company's recurring
                // expense failing (a locked accounting period, a
                // deactivated posting account, ...) must not also block
                // every other company's due recurring expense in this
                // same run — each is now independent, and the failed one
                // simply retries on the next scheduled run since its
                // own next_run_date never advanced.
                Log::error("Failed to generate expense from recurring expense #{$recurringExpense->id}: {$e->getMessage()}");
            }
        }

        $this->info("Generated {$generated} expense(s) from recurring expenses.");

        return self::SUCCESS;
    }
}
