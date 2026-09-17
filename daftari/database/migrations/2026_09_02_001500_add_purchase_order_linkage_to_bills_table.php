<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Purchase Order can now be billed across several deliveries rather
 * than in one all-or-nothing conversion — bills.purchase_order_id names
 * the order a bill was raised against (informational, for filtering/
 * display); the actual per-line fulfillment tracking lives on
 * bill_items.purchase_order_item_id (see the companion migration).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_id');
        });
    }
};
