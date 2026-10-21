<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding CASH-06: Expenses, Purchase Orders, Invoices, Quotations,
 * and (as of this engagement) Manual Journal Entries can all require
 * sign-off above a threshold — Payment Vouchers, Receipt Vouchers, and
 * Bills (the actual movement of cash) could not. Mirrors the existing
 * pattern exactly: null threshold = disabled, today's instant-post
 * behavior unchanged for every company until an owner opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('payment_voucher_approval_threshold', 12, 2)->nullable()->after('manual_journal_approval_threshold');
            $table->decimal('receipt_voucher_approval_threshold', 12, 2)->nullable()->after('payment_voucher_approval_threshold');
            $table->decimal('bill_approval_threshold', 12, 2)->nullable()->after('receipt_voucher_approval_threshold');
        });

        Schema::table('payment_vouchers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });

        Schema::table('receipt_vouchers', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['payment_voucher_approval_threshold', 'receipt_voucher_approval_threshold', 'bill_approval_threshold']);
        });

        foreach (['payment_vouchers', 'receipt_vouchers', 'bills'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('approved_by');
                $table->dropColumn(['approved_at', 'rejection_reason']);
            });
        }
    }
};
