<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every document type that carries money (Invoice, Quotation,
 * PurchaseOrder, Expense, Bill) already has an optional project_id, but
 * the actual cash movement records — ReceiptVoucher, PaymentVoucher,
 * BankTransfer — never did, so there was no way to answer "how much cash
 * has this project actually received/paid/moved" even though the
 * underlying documents were already project-tagged. This closes that gap
 * for the new Project Cash Flow module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('to_bank_account_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bank_transfers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
