<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            // Set when a low-stock alert has already fired for this
            // item/warehouse pair and cleared once it's replenished above
            // its reorder point — so the daily check only re-notifies on a
            // fresh dip, not every day stock stays low.
            $table->timestamp('low_stock_notified_at')->nullable()->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('item_stocks', function (Blueprint $table) {
            $table->dropColumn('low_stock_notified_at');
        });
    }
};
