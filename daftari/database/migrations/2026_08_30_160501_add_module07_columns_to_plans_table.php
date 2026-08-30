<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // "Products" limit — the app calls them Items internally
            // (Item model, items table), so the column follows that name.
            $table->unsignedInteger('max_items')->nullable()->after('max_storage_mb');
            $table->unsignedInteger('max_api_calls_per_month')->nullable()->after('max_items');

            // New module-level feature toggles (Module 07). ZATCA already
            // has has_zatca_phase2; "Multi-branch" is derived from
            // max_branches (>1 or unlimited) rather than a separate flag.
            $table->boolean('has_api')->default(false)->after('has_zatca_phase2');
            $table->boolean('has_whatsapp')->default(false)->after('has_api');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['max_items', 'max_api_calls_per_month', 'has_api', 'has_whatsapp']);
        });
    }
};
