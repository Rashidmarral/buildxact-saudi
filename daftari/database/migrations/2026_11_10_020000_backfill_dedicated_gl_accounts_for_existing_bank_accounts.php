<?php

use App\Models\BankAccount;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives every BankAccount that existed before the account_id column was
 * added its own dedicated ledger line, so per-bank GL routing (see
 * LedgerPostingService::bankOrCashAccount()) applies immediately, not
 * only once someone happens to edit that account.
 *
 * Deliberately does NOT move any money: the shared Default Bank/Default
 * Cash account keeps every balance it already has from before this
 * migration — this only creates new, zero-balance ledger lines that
 * FUTURE transactions will post to. See BankAccountController's
 * "GL opening balance" field for the separate, opt-in, user-reviewed way
 * to move a starting balance into one of these new lines — splitting
 * historical totals between banks isn't something code should guess at
 * silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        BankAccount::withoutGlobalScope('company')
            ->whereNull('account_id')
            ->get()
            ->each(fn (BankAccount $bankAccount) => $bankAccount->glAccount());
    }

    public function down(): void
    {
        // Backfill only — intentionally not reversed, since postings may
        // already reference these new accounts by the time this would run.
    }
};
