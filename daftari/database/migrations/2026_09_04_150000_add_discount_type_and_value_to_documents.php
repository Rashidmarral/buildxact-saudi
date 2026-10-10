<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Discount on an invoice/quotation/bill/purchase order/recurring invoice
 * was always a flat SAR amount entered straight into discount_total.
 * These add the raw input (discount_value) and how to interpret it
 * (discount_type: 'fixed' or 'percentage') so the UI can offer a
 * percentage option — discount_total itself stays the derived absolute
 * amount everything downstream (ZATCA XML, ledger posting, totals,
 * reports) already expects, computed from these two by
 * recalculateTotals() rather than trusted as raw client input.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'quotations', 'bills', 'purchase_orders', 'recurring_invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->string('discount_type', 20)->default('fixed')->after('discount_total');
                $blueprint->decimal('discount_value', 12, 2)->default(0)->after('discount_type');
            });

            // Every existing row's discount was a flat SAR amount — the new
            // discount_value column defaults to 0, so it must be backfilled
            // from discount_total or every existing fixed discount would
            // silently disappear the next time its document is recalculated.
            DB::table($table)->where('discount_total', '>', 0)
                ->update(['discount_value' => DB::raw('discount_total')]);
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'quotations', 'bills', 'purchase_orders', 'recurring_invoices'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['discount_type', 'discount_value']);
            });
        }
    }
};
