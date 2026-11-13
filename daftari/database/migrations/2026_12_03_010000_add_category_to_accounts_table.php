<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hamly-parity gap: the Trial Balance and Balance Sheet group accounts
 * under sub-headings ("Cash and cash equivalents", "Accounts
 * Receivable", "Current Liabilities", ...) with their own subtotals —
 * `type` alone (asset/liability/equity/revenue/expense) is too coarse
 * for that grouping. Nullable and nothing-changing for a custom account
 * a company created itself; Account::categoryForCode() supplies the
 * value for every system account, see the companion backfill migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->string('category', 60)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }
};
