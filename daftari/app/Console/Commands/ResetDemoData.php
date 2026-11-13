<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

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
 *
 * On SQLite (tests), accounts.parent_account_id (self-referencing,
 * added for the Chart of Accounts tree view) makes SQLite's own FK-
 * cascade machinery throw "FOREIGN KEY constraint failed" on this
 * delete even when every parent_account_id is null — SQLite checks
 * the self-referencing constraint immediately, mid-cascade, rather
 * than after the whole statement's cascade finishes. Deferring FK
 * checks to the end of this statement's implicit transaction (SQLite-
 * only; the pragma doesn't exist in MySQL/production) is exactly
 * what fixes that ordering, and it reverts on its own once the
 * transaction commits, so nothing lingers past this one delete.
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

            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::statement('PRAGMA defer_foreign_keys = ON');
            }

            $company->delete();

            $this->info("Removed \"{$company->name}\": {$userCount} user(s) and all associated demo data.");
        }

        return self::SUCCESS;
    }
}
