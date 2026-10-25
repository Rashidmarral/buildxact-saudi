<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding INV-04: the item profitability report recomputed cost
 * from each item's CURRENT purchase_price, even for a sale from months
 * ago — if the item's cost has since changed, a report for a past period
 * silently shows today's margin, not the margin that sale actually had.
 * This column freezes the per-unit cost at the moment the invoice line
 * was created (mirroring the existing name_ar/item_description snapshot
 * pattern on this same table), so a historical report can read back the
 * cost basis that was actually true at the time. Nullable: existing rows
 * created before this migration have no snapshot and fall back to the
 * item's current purchase_price — a known, documented approximation for
 * pre-migration data only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 14, 4)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
