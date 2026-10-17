<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZatcaSyncService::nextIcv() used to compute the mandatory Invoice
 * Counter Value by counting every zatca_invoice_logs/zatca_credit_note_logs/
 * zatca_debit_note_logs row for the company, with no `environment` filter
 * — pooling developer/simulation/production into one counter even
 * though the PIH hash chain (zatca_last_invoice_hash) is already
 * correctly scoped per environment via Company::switchZatcaEnvironment().
 * It also meant a company that reset ZATCA onboarding (now correctly
 * clearing the hash chain back to genesis — see the prior migration/fix)
 * would still have its ICV inherited from log rows written under the
 * discarded identity, an inconsistent ICV/PIH pair ZATCA's own clearance
 * validation is built to reject.
 *
 * Replaces the derived (COUNT * + 1) with an explicit, persisted
 * per-environment counter — advanced only on confirmed success, in the
 * exact same update() call that already advances zatca_last_invoice_hash
 * — so ICV and the hash chain move together atomically, and a reset
 * onboarding resetting one always means resetting the other. See the
 * companion data migration for how existing companies' in-progress
 * chains are backfilled so this doesn't change anyone's next real ICV.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('zatca_icv_counter')->default(0)->after('zatca_last_sync_at');
        });

        Schema::table('zatca_environment_credentials', function (Blueprint $table) {
            $table->unsignedInteger('icv_counter')->default(0)->after('last_invoice_hash');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('zatca_icv_counter');
        });

        Schema::table('zatca_environment_credentials', function (Blueprint $table) {
            $table->dropColumn('icv_counter');
        });
    }
};
