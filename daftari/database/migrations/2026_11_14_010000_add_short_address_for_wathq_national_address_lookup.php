<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Saudi National Address "short address" (4 letters + 4 digits, e.g.
 * RRRD2929) is what the Wathq national-address lookup takes as input —
 * see WathqService::lookupNationalAddress(). Stored next to each entity's
 * existing structured address fields so the "Verify address" button has
 * somewhere to read its input from and remember it afterward.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('short_address', 10)->nullable()->after('additional_number');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('additional_number', 20)->nullable()->after('postal_code');
            $table->string('short_address', 10)->nullable()->after('additional_number');
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->string('additional_number', 20)->nullable()->after('postal_code');
            $table->string('short_address', 10)->nullable()->after('additional_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('short_address');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['additional_number', 'short_address']);
        });

        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropColumn(['additional_number', 'short_address']);
        });
    }
};
