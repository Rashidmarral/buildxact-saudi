<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "remove payment status selection from issued invoice details page" —
 * the cosmetic payment_status_override this column backed let a user
 * hand-pick a Paid/Pending label on an invoice's header that had no
 * relationship to its real status, so the same invoice could read
 * "Paid" on its own page while the invoice list (which always showed
 * the real status) still read "Sent" — confusing and, per the report
 * that prompted this migration, actively misleading. Retired entirely
 * rather than just hiding the selector, so every page always shows the
 * one real status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_status_override');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payment_status_override', 20)->nullable()->after('status');
        });
    }
};
