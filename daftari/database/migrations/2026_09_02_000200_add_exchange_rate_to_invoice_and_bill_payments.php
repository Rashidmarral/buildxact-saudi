<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial audit finding: FX_GAINS/FX_LOSSES accounts were mappable in
 * the chart of accounts but nothing ever posted to them — every payment
 * converted at the invoice's/bill's own booked exchange_rate, so a real
 * rate movement between issue and settlement was silently absorbed
 * instead of recognized. Payments now carry their own rate (defaulting to
 * the document's, so same-currency and unset-rate companies see no
 * change) so LedgerPostingService can post the difference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('amount');
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });

        Schema::table('bill_payments', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
