<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Extend existing Quotation with won/lost outcome + margin (cost-price
 * per line) rather than a duplicate model" — Hamly's separate
 * Estimates/tenders concept, folded into the Quotation this app already
 * has (which already tracks won/lost via its draft/issued/accepted/
 * rejected/converted status and expiry via isExpired() — see the model).
 * The one real gap was margin visibility: an internal cost price per
 * line, optional, never shown on the client-facing PDF/portal, so a
 * quoted job's margin is visible before it's won, not just after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->decimal('cost_price', 14, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('cost_price');
        });
    }
};
