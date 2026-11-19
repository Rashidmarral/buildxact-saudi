<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

/**
 * Account::seedSystemAccounts() now passes `category` to firstOrCreate(),
 * but that only ever sets it on the CREATE branch — every system account
 * a company already had before the companion 2026_12_03_010000 migration
 * added the column still has category === null. Re-deriving it from the
 * same code map here (rather than re-running seedSystemAccounts(), which
 * would no-op on an existing row) is the only way those rows actually
 * get backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Account::withoutGlobalScope('company')
            ->where('is_system', true)
            ->whereNull('category')
            ->get(['id', 'code'])
            ->each(function (Account $account) {
                if ($category = Account::categoryForCode($account->code)) {
                    $account->update(['category' => $category]);
                }
            });
    }

    public function down(): void
    {
        // Backfill only — intentionally not reversed.
    }
};
