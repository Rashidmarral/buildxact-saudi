<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A single top-level "how much ZATCA integration is on" switch — Disabled
 * / Phase 1 (QR only) / Phase 2 (FATOORA) — inspired by how SalePro POS
 * presents this choice, layered on top of the existing onboarding engine
 * rather than replacing it. See App\Models\Company::ZATCA_MODE_* and
 * isZatcaOnboarded(), which now also requires phase2 mode.
 *
 * Backfill keeps every existing company's real-world behavior identical:
 * a company that has ever generated a CSR (or further) already relies on
 * Phase 2 sync actually running, so it gets 'phase2'; everyone else keeps
 * getting the always-on Phase 1 QR they already had, unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('zatca_integration_mode', 20)->default('phase1')->after('zatca_sync_b2c');
        });

        DB::table('companies')
            ->where('zatca_onboarding_status', '!=', 'not_started')
            ->update(['zatca_integration_mode' => 'phase2']);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('zatca_integration_mode');
        });
    }
};
