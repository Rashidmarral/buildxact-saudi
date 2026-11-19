<?php

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

/**
 * New companies get the "Internal Cost Recovery" system account (code
 * 5950) and its INTERNAL_COST_RECOVERY mapping automatically via
 * Account::seedSystemAccounts()/AccountMapping::seedDefaults() at
 * creation. A company that already existed before this account was
 * added never runs that again on its own, so it needs its chart of
 * accounts and mapping backfilled once here — both calls are
 * idempotent (firstOrCreate by code/key), mirroring the
 * 2026_10_26_020000 employee-advances backfill.
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
