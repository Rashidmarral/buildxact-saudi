<?php

use App\Models\Account;
use Illuminate\Database\Migrations\Migration;

/**
 * Account::categoryForCode() now splits Accounts Payable (2000) out of
 * "Current Liabilities" into its own category, and Other Income/FX Gain
 * (4200, 9100) out of "Revenue" into "Other Income" — mirroring Hamly's
 * own grouping, which a company comparing the two read as those
 * categories being outright missing from the Trial Balance. Re-derives
 * the category for exactly these three codes on every company that
 * already seeded them under the old grouping (seedSystemAccounts()
 * itself only sets `category` on the CREATE branch, so an existing row
 * needs this same re-derive-and-update approach the companion
 * 2026_12_03_020000 migration already established).
 */
return new class extends Migration
{
    public function up(): void
    {
        Account::withoutGlobalScope('company')
            ->where('is_system', true)
            ->whereIn('code', ['2000', '4200', '9100'])
            ->get(['id', 'code'])
            ->each(function (Account $account) {
                $account->update(['category' => Account::categoryForCode($account->code)]);
            });
    }

    public function down(): void
    {
        // Re-derive only — intentionally not reversed.
    }
};
