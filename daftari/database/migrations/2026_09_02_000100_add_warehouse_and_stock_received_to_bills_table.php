<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commercial audit finding: posting a bill capitalized cost to the ledger
 * but never touched physical stock — the only way ItemStock.quantity ever
 * increased was a disconnected manual Stock Adjustment. This lets a bill
 * optionally name the warehouse it's received into, mirroring
 * invoices.warehouse_id/stock_deducted for sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('warehouse_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->boolean('stock_received')->default(false)->after('warehouse_id');
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropColumn('stock_received');
        });
    }
};
