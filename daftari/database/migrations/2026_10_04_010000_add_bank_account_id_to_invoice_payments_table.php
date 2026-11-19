<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Without this, a recorded invoice payment knew only a generic method
 * (cash/bank_transfer/card/other) — never which actual BankAccount the
 * money landed in. That meant it posted to a generic default Cash/Bank GL
 * account rather than the real one, and it was invisible on both the
 * Bank Account statement and the Project Cash Flow statement (both built
 * entirely from ReceiptVoucher/PaymentVoucher/BankTransfer/Expense —
 * never InvoicePayment), making a genuinely received client payment
 * disappear from a project's own cash-flow record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('invoice_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
