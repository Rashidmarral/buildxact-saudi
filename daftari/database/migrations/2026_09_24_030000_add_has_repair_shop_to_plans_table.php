<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repair Shop joins Payroll/POS/Restaurant as a Module 07 'gated'
     * feature (see FeatureRegistry) — off for every plan by default,
     * installed per company through the Modules marketplace.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_repair_shop')->default(false)->after('has_restaurant');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_repair_shop');
        });
    }
};
