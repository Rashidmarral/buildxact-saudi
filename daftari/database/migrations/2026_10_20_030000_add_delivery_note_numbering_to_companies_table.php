<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Same {doc}_prefix / next_{doc}_number sequential-numbering pattern as
 * every other document type on this table (invoice_prefix, quotation_
 * prefix, etc.) — see Company::nextSequenceNumber().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // 'DN' is already the debit note prefix on this table — 'DO'
            // (Delivery Order) avoids two different document types
            // printing visually identical numbers.
            $table->string('delivery_note_prefix', 10)->default('DO');
            $table->unsignedInteger('next_delivery_note_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['delivery_note_prefix', 'next_delivery_note_number']);
        });
    }
};
