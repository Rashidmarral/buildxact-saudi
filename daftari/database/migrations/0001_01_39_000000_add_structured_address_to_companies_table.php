<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // ZATCA Phase 2 requires the seller's PostalAddress broken into
            // these discrete fields (StreetName, BuildingNumber,
            // CitySubdivisionName, PostalZone) — the existing free-text
            // `address` column alone can't satisfy the invoice XML schema.
            // Mirrors the structured fields already on `clients`.
            $table->string('building_number', 20)->nullable()->after('address');
            $table->string('street_name')->nullable()->after('building_number');
            $table->string('district')->nullable()->after('street_name');
            $table->string('postal_code', 20)->nullable()->after('district');
            $table->string('additional_number', 20)->nullable()->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['building_number', 'street_name', 'district', 'postal_code', 'additional_number']);
        });
    }
};
