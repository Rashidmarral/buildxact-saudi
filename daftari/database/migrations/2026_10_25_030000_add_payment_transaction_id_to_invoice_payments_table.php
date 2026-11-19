<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding PAY-01: PaymentSettlementService::settleInvoice() had no
 * way to tell "this gateway transaction already created its invoice
 * payment" from "it never has" — it unconditionally created a new
 * InvoicePayment every time it ran. This column is the idempotency anchor
 * that check now uses, mirroring payments.payment_transaction_id (added
 * earlier for the exact same reason on the subscription side).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_transaction_id');
        });
    }
};
