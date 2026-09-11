<?php

namespace App\Console\Commands;

use App\Models\RecurringJournalEntry;
use Illuminate\Console\Command;

class GenerateRecurringJournalEntries extends Command
{
    protected $signature = 'journals:generate-recurring';

    protected $description = 'Post a journal entry for every active recurring journal entry whose next run date has arrived';

    public function handle(): int
    {
        $due = RecurringJournalEntry::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereDate('next_run_date', '<=', now()->toDateString())
            ->with('lines', 'company')
            ->get();

        $posted = 0;

        foreach ($due as $recurringEntry) {
            try {
                $recurringEntry->generateEntry();
                $posted++;
            } catch (\Throwable $e) {
                // A locked accounting period or an account deactivated since
                // the recurrence was created shouldn't silently skip every
                // later run — leave next_run_date untouched so this same
                // recurrence is retried tomorrow, and surface the failure.
                $this->error("Recurring journal entry #{$recurringEntry->id} ({$recurringEntry->title}) failed: {$e->getMessage()}");
            }
        }

        $this->info("Posted {$posted} journal entry(ies) from recurring journal entries.");

        return self::SUCCESS;
    }
}
