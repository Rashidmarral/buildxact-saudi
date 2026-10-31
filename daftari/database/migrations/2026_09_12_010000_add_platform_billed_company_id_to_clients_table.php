<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a Client row to the tenant Company it represents when that Client
 * was auto-created by PlatformInvoiceService to bill that company for its
 * own Daftari subscription (see Setting 'platform_billing_company_id').
 * Deliberately not a foreign key to `companies` — the Client's own
 * `company_id` already points at the *billing* company (whichever tenant
 * the operator designated as their own business), and this column points
 * at an entirely different company (the *paying* tenant); constraining it
 * would be a legitimate cross-tenant reference, which the app's global
 * company scoping isn't set up to reason about safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->unsignedBigInteger('platform_billed_company_id')->nullable()->after('company_id');
            $table->index('platform_billed_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropIndex(['platform_billed_company_id']);
            $table->dropColumn('platform_billed_company_id');
        });
    }
};
