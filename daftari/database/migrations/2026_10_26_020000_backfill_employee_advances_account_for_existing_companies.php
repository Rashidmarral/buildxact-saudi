<?php

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

/**
 * New companies get the "Due from Employees (Advances)" system account
 * (code 1260) and its EMPLOYEE_ADVANCES mapping automatically via
 * Account::seedSystemAccounts()/AccountMapping::seedDefaults() at
 * creation. A company that already existed before this account was
 * added never runs that again on its own, so it needs its chart of
 * accounts and mapping backfilled once here — both calls are
 * idempotent (firstOrCreate by code/key), so re-running this for a
 * company that already has every other system account only creates
 * the one that's missing.
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
