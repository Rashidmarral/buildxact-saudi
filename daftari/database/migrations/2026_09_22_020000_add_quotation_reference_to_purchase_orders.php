<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a Purchase Order reference the sub-vendor's own quotation number
 * (e.g. "0003") — the PO PDF's greeting paragraph then reads "With
 * reference to the quotation submitted by you numbered (0003), ..." the
 * same way the real reference document a user shared did, instead of the
 * generic fallback sentence. Optional: a PO placed without ever having
 * received a formal quotation number just keeps the generic wording.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('quotation_reference', 60)->nullable()->after('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('quotation_reference');
        });
    }
};
