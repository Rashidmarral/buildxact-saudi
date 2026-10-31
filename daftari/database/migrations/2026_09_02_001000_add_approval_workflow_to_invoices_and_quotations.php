<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial audit finding: purchase orders and expenses could require
 * sign-off above a threshold, but sales-side documents could not — anyone
 * who could create an invoice could send it (and post it to the ledger)
 * or issue a quotation, however large. Mirrors the existing PO/expense
 * approval pattern: null/0 threshold = disabled, today's instant-send
 * behavior unchanged for every company until an owner opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('invoice_approval_threshold', 12, 2)->nullable()->after('expense_approval_threshold');
            $table->decimal('quotation_approval_threshold', 12, 2)->nullable()->after('invoice_approval_threshold');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('approved_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('approval_rejection_reason')->nullable()->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['invoice_approval_threshold', 'quotation_approval_threshold']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'rejection_reason']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['approved_at', 'approval_rejection_reason']);
        });
    }
};
