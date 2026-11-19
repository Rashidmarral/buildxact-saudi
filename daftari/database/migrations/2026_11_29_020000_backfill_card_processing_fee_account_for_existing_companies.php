<?php

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

/**
 * New companies get the "Bank & Card Processing Fees" system account
 * (code 5180) and its CARD_PROCESSING_FEE_EXPENSE mapping automatically
 * via Account::seedSystemAccounts()/AccountMapping::seedDefaults() at
 * creation. A company that already existed before this account was
 * added never runs that again on its own, so it needs its chart of
 * accounts and mapping backfilled once here — both calls are
 * idempotent (firstOrCreate by code/key), mirroring the
 * 2026_11_09_010000 internal-cost-recovery backfill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Company::query()->pluck('id')->each(function (int $companyId) {
            Account::seedSystemAccounts($companyId);
            AccountMapping::seedDefaults($companyId);
        });
    }

    public function down(): void
    {
        // Backfill only — intentionally not reversed, since other
        // companies' real journal entries may already reference the
        // account by the time this would run.
    }
};
