<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Projects previously only tracked revenue via Invoice and cost via
 * Expense, so a subcontracted job (quote a client, then place a Purchase
 * Order with a sub-vendor for the same scope) had no way to show true
 * project margin — the sub-vendor's PO/Bill simply wasn't linkable to the
 * project at all. This adds the same optional project_id every other
 * document type already has (see Invoice) to Quotation, PurchaseOrder,
 * and Bill.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }
};
