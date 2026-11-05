<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The CSR subject fields (Common Name, Organization Unit Name, Business
 * Category) were previously computed in code — Common Name/Organization
 * Unit fell back to the company's VAT number, which isn't what ZATCA's own
 * CSR samples use (Organization Unit is meant to be a branch/group
 * identifier, Common Name a solution-specific label) and gave every
 * company the exact same values with no way to correct them per company.
 * These become real per-company, user-editable fields instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('zatca_common_name')->nullable()->after('zatca_egs_serial');
            $table->string('zatca_organization_unit_name')->nullable()->after('zatca_common_name');
            $table->string('zatca_business_category')->nullable()->after('zatca_organization_unit_name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['zatca_common_name', 'zatca_organization_unit_name', 'zatca_business_category']);
        });
    }
};
