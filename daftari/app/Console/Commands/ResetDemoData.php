<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Permanently removes every demo company and all of its data (Module 23)
 * — the "easy to remove/reset" half of demo mode. Only ever touches
 * companies flagged is_demo=true; every other company's data is left
 * completely untouched.
 *
 * users.company_id uses nullOnDelete() (a real user is meant to survive
 * its company being deleted), so demo users are deleted explicitly first
 * — otherwise they'd be left behind as orphaned, company-less rows.
 * Every other table's company_id foreign key cascades on delete, so
 * deleting the Company row itself cleans up the rest: clients, suppliers,
 * items, invoices, expenses, payments, and so on.
 */
class ResetDemoData extends Command
{
    protected $signature = 'demo:reset {--force : Skip the confirmation prompt}';

    protected $description = 'Permanently delete every demo company and all of its data — never touches real customer data';

    public function handle(): int
    {
        $demoCompanies = Company::where('is_demo', true)->get();

        if ($demoCompanies->isEmpty()) {
            $this->info('No demo company found — nothing to reset.');

            return self::SUCCESS;
        }

        foreach ($demoCompanies as $company) {
            if (! $this->option('force') && ! $this->confirm("Delete demo company \"{$company->name}\" and all of its data? This cannot be undone.")) {
                $this->warn("Skipped \"{$company->name}\".");

                continue;
            }

            $userCount = User::where('company_id', $company->id)->count();
            User::where('company_id', $company->id)->delete();
            $company->delete();

            $this->info("Removed \"{$company->name}\": {$userCount} user(s) and all associated demo data.");
        }

        return self::SUCCESS;
    }
}
