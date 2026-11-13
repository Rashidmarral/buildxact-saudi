<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // A purely cosmetic override for the Paid/Pending/Partially
            // paid/Overdue badge shown on the invoice header and on the
            // printed/client-facing document — never read by balanceDue(),
            // ledger postings, credit/debit note eligibility, or ZATCA sync
            // guards, which all keep using the real status/amount_paid.
            // Cleared automatically the next time a real payment is
            // recorded (see InvoiceController::storePayment()/updatePayment()),
            // so real data always wins once it's entered.
            $table->string('payment_status_override', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('payment_status_override');
        });
    }
};
