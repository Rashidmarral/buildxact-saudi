<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Restaurant Management joins Payroll/POS as a Module 07 'gated'
     * feature (see FeatureRegistry) — off for every plan by default, so
     * it's purely an admin-installed, per-company add-on (CompanyOverride)
     * rather than something any plan grants automatically.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_restaurant')->default(false)->after('has_pos');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('has_restaurant');
        });
    }
};
