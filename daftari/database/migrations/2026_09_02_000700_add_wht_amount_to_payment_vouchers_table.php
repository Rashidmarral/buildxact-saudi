<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The withholding tax portion of this voucher's underlying bill, applied
 * on the first voucher paid against a WHT-bearing bill — separate from
 * `amount` (the actual cash transferred) so LedgerPostingService can
 * credit WHT Payable for it instead of Bank/Cash.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->decimal('wht_amount', 12, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropColumn('wht_amount');
        });
    }
};
