<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "add more... Cost Centre settings enhancements" — a company can now
 * require every sent invoice / posted bill to carry a cost centre tag
 * before it's allowed through (cost_center_required_on_sales/
 * _on_expenses), and can narrow which cost centre types it actually
 * uses (cost_center_scope — a comma-separated subset of department/
 * project/branch; null means "all", matching every company's current
 * behavior exactly).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('cost_center_required_on_sales')->default(false)->after('invoice_dunning_enabled');
            $table->boolean('cost_center_required_on_expenses')->default(false)->after('cost_center_required_on_sales');
            $table->string('cost_center_scope')->nullable()->after('cost_center_required_on_expenses');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['cost_center_required_on_sales', 'cost_center_required_on_expenses', 'cost_center_scope']);
        });
    }
};
