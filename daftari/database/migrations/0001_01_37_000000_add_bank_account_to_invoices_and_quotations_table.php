<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
            $table->decimal('retention_rate', 5, 2)->default(0)->after('discount_total');
            $table->decimal('retention_amount', 12, 2)->default(0)->after('retention_rate');
            $table->boolean('stock_deducted')->default(false)->after('warehouse_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('client_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['retention_rate', 'retention_amount', 'stock_deducted']);
            $table->dropConstrainedForeignId('warehouse_id');
            $table->dropConstrainedForeignId('bank_account_id');
        });
    }
};
