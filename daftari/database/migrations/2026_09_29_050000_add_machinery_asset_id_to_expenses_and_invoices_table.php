<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets fuel/maintenance/operator-wage Expenses and rental/sale Invoices
 * tag the specific machine they're for, exactly mirroring how project_id
 * was added to both tables before — reuses the existing approval/GL/
 * invoicing pipelines untouched instead of a parallel revenue/expense
 * ledger for machinery.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('machinery_asset_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('machinery_asset_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machinery_asset_id');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('machinery_asset_id');
        });
    }
};
