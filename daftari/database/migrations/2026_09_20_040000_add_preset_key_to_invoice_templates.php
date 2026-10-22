<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the new gallery-style template picker: clicking "Activate" on a
 * built-in preset should re-use the same InvoiceTemplate row on every
 * click (so re-activating doesn't pile up duplicate clones), keyed by
 * which preset it was created from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->string('preset_key', 40)->nullable()->after('document_type');
            $table->unique(['company_id', 'preset_key']);
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'preset_key']);
            $table->dropColumn('preset_key');
        });
    }
};
