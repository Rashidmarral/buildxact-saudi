<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rate is "1 unit of document currency = X units of the company's
        // base currency" — the ledger is always posted in base currency, so
        // every FX-denominated document carries the rate it was created
        // with, and old entries never silently reprice.
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->decimal('exchange_rate', 12, 6)->default(1)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->dropColumn('exchange_rate');
        });
    }
};
