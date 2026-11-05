<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Payroll and POS move from FeatureRegistry's 'planned' (always off,
     * no such module exists) to 'gated' (a real per-plan toggle), same
     * shape as has_api/has_whatsapp before them.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_payroll')->default(false)->after('has_whatsapp');
            $table->boolean('has_pos')->default(false)->after('has_payroll');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['has_payroll', 'has_pos']);
        });
    }
};
