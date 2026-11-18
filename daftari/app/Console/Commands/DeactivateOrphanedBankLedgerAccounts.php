<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Console\Command;

/**
 * One-time catch-up for every bank account deleted BEFORE
 * BankAccountController::destroy() started deactivating its ledger line
 * (see that controller's commit) — those already-deleted bank accounts
 * left their dedicated Account row (BankAccount::glAccount() always
 * creates it with is_system=false, in the 1001-1099/1101-1149 reserved
 * sub-ranges — never a shared/system account) still active, so it kept
 * showing up in Trial Balance, General Ledger, and every report's
 * account filter with no way to trace it back to a bank account that no
 * longer exists.
 *
 * Only ever touches a non-system account in those exact reserved ranges
 * that no bank_accounts row currently points to — by construction,
 * every row it finds is an orphaned former bank-account ledger line,
 * never a system account or one still backing a real bank account.
 */
class DeactivateOrphanedBankLedgerAccounts extends Command
{
    protected $signature = 'accounts:deactivate-orphaned-bank-lines
        {--company= : Only this company\'s id — omit to scan every company}
        {--dry-run : List what would be deactivated without changing anything}';

    protected $description = 'Deactivate chart-of-accounts ledger lines left behind by a deleted bank account';

    public function handle(): int
    {
        $companies = $this->option('company')
            ? Company::where('id', (int) $this->option('company'))->get()
            : Company::all();

        if ($companies->isEmpty()) {
            $this->error('No matching company found.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($companies as $company) {
            // withoutGlobalScope('company') on both: BelongsToCompany's
            // scope filters by the currently-authenticated user's
            // company_id whenever one happens to be logged in (never
            // true for a real SSH-invoked `php artisan` run, but this
            // command explicitly scopes by $company->id itself anyway,
            // so it shouldn't depend on that ambient assumption holding).
            $linkedAccountIds = BankAccount::withoutGlobalScope('company')
                ->where('company_id', $company->id)->whereNotNull('account_id')->pluck('account_id');

            // `code` is a string column — filtered numerically in PHP
            // rather than with a SQL CAST, same reasoning as
            // Account::nextAvailableCode()'s own range lookup.
            $orphans = Account::withoutGlobalScope('company')
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->where('is_system', false)
                ->whereNotIn('id', $linkedAccountIds)
                ->get()
                ->filter(function (Account $account) {
                    $code = (int) $account->code;

                    return ($code >= 1001 && $code <= 1099) || ($code >= 1101 && $code <= 1149);
                });

            foreach ($orphans as $account) {
                $this->line("[{$company->name}] {$account->code} {$account->name}".($dryRun ? ' (would deactivate)' : ' — deactivated'));

                if (! $dryRun) {
                    $account->update(['is_active' => false]);
                }

                $total++;
            }
        }

        $this->info($dryRun
            ? "Found {$total} orphaned bank/cash ledger line(s). Re-run without --dry-run to deactivate them."
            : "Deactivated {$total} orphaned bank/cash ledger line(s).");

        return self::SUCCESS;
    }
}
