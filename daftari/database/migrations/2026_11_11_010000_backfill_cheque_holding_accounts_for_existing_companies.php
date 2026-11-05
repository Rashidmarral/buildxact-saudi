<?php

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use Illuminate\Database\Migrations\Migration;

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
        // Backfill only — intentionally not reversed, since real journal
        // entries may already reference the account.
    }
};
