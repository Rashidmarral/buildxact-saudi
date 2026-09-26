<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding MEDIUM-12: PurchaseOrderController::convertToBill() used
 * to copy a PO's full line quantities into exactly one Bill and then
 * permanently mark the PO 'converted', with nothing tracking how much of
 * each line had actually been billed — a second bill against the same PO
 * was structurally impossible, and there was no way to see or enforce
 * that a bill never bills more than was ordered. Each bill line now
 * optionally names the PO line it fulfills; PurchaseOrderItem sums its
 * linked (non-void) bill lines to compute what's still remaining, which
 * is both the over-billing guard and the "X of Y received" display.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->after('item_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchase_order_item_id');
        });
    }
};
