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
            $table->string('machinery_prefix', 10)->default('EQ');
            $table->unsignedInteger('next_machinery_number')->default(1);
            $table->string('rental_contract_prefix', 10)->default('RC');
            $table->unsignedInteger('next_rental_contract_number')->default(1);
            $table->string('letter_prefix', 10)->default('LTR');
            $table->unsignedInteger('next_letter_number')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'machinery_prefix', 'next_machinery_number',
                'rental_contract_prefix', 'next_rental_contract_number',
                'letter_prefix', 'next_letter_number',
            ]);
        });
    }
};
