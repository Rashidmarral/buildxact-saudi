<?php

namespace App\Services\Admin;

use App\Models\Account;
use App\Models\BankAccount;
use App\Models\Company;
use Illuminate\Support\Collection;

/**
 * Finds and deactivates a chart-of-accounts ledger line left behind by a
 * bank account that was deleted BEFORE BankAccountController::destroy()
 * started deactivating it itself (every bank account deleted since that
 * fix is already handled there — this only ever has anything to find for
 * one deleted before it shipped). BankAccount::glAccount() always
 * creates that ledger line with is_system=false, in the exact
 * 1001-1099 (cash) or 1101-1149 (bank) reserved sub-ranges — never a
 * shared/system account — so a non-system account in those ranges that
 * no bank_accounts row points to is, by construction, always one of
 * these orphans, never a real company-added account.
 *
 * Shared by the super-admin "Danger zone" UI
 * (Admin\CompanyController::show()/deactivateOrphanedBankAccounts()) and
 * the accounts:deactivate-orphaned-bank-lines console command, so a host
 * where artisan isn't reachable (shared hosting with SSH disabled, for
 * instance) still has a way to run this.
 */
class OrphanedBankLedgerAccountService
{
    /**
     * @return Collection<int, Account>
     */
    public function preview(Company $company): Collection
    {
        $linkedAccountIds = BankAccount::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->whereNotNull('account_id')
            ->pluck('account_id');

        // `code` is a string column — filtered numerically in PHP rather
        // than with a SQL CAST, same reasoning as
        // Account::nextAvailableCode()'s own range lookup.
        return Account::withoutGlobalScope('company')
            ->where('company_id', $company->id)
            ->where('is_active', true)
            ->where('is_system', false)
            ->whereNotIn('id', $linkedAccountIds)
            ->orderBy('code')
            ->get()
            ->filter(function (Account $account) {
                $code = (int) $account->code;

                return ($code >= 1001 && $code <= 1099) || ($code >= 1101 && $code <= 1149);
            })
            ->values();
    }

    /**
     * Deactivates every orphan preview() finds for this company and
     * returns how many were changed.
     */
    public function execute(Company $company): int
    {
        $orphans = $this->preview($company);

        foreach ($orphans as $account) {
            $account->update(['is_active' => false]);
        }

        return $orphans->count();
    }
}
