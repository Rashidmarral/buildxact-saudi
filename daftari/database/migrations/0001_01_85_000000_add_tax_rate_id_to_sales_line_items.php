<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a sales-side line reference the exact tax rate it was charged
 * under — not just its bare percentage — so zero-rated (0%, still a
 * taxable supply) and exempt (0%, outside VAT entirely) can be told apart
 * again downstream, e.g. by ZatcaXmlGenerator when picking the e-invoice
 * tax category code (S/Z/E). Nullable and never required: every existing
 * row, and every line-item table not touched here (quotations, bills,
 * purchase orders/returns — none of which ZATCA ever sees), keeps
 * working exactly as before off vat_rate alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('vat_rate')->constrained()->nullOnDelete();
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->foreignId('tax_rate_id')->nullable()->after('vat_rate')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });

        Schema::table('credit_note_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_rate_id');
        });
    }
};
