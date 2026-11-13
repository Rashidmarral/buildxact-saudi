<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // null on any of these means "use the platform default" (the
            // superadmin-configured Setting) — the same options the
            // platform-wide General settings screen offers, but now
            // overridable per company instead of forced uniformly onto
            // every tenant.
            $table->string('date_format', 10)->nullable()->after('locale');
            $table->string('time_format', 5)->nullable()->after('date_format');
            $table->unsignedTinyInteger('fiscal_year_start')->nullable()->after('time_format');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['date_format', 'time_format', 'fiscal_year_start']);
        });
    }
};
