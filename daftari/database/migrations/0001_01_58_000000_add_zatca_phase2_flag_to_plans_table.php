<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Phase 1 (QR code on every invoice) is a baseline legal
            // requirement generated unconditionally for every company
            // regardless of plan — see Invoice::recalculateTotals(). Phase 2
            // (CSR/CSID onboarding and real-time clearance/reporting with
            // ZATCA's API) is the tiered feature this flag gates.
            $table->boolean('has_zatca_phase2')->default(false)->after('has_roles_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_zatca_phase2');
        });
    }
};
