<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial audit finding: nothing ever stopped a user from posting or
 * editing a transaction dated inside a period that had already been
 * closed and reported — a bookkeeper could quietly alter last quarter's
 * VAT return after it was filed. A company-level lock date, enforced in
 * LedgerPostingService::post(), closes that gap.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->date('accounting_lock_date')->nullable()->after('expense_approval_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('accounting_lock_date');
        });
    }
};
