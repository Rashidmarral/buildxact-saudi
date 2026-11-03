<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "please make a system so i can off these features from super admin" —
 * unlike every other has_* plan column (which defaults false, since
 * those modules are opt-in add-ons new to the plan), these two default
 * TRUE: Subcontractor Payments and Project Manager Budgets already
 * ship to every company today with no plan gate at all, so a false
 * default here would silently lock every existing company with an
 * active subscription out of data they already rely on the moment
 * this migration runs. True keeps today's behavior unchanged; a Super
 * Admin opts a specific company OUT via the existing per-company
 * CompanyOverride screen (Admin > Companies > a company > Features),
 * or kills it platform-wide via Admin > Platform Settings > Features.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('has_subcontractor_payments')->default(true)->after('has_client_statements');
            $table->boolean('has_project_manager_budgets')->default(true)->after('has_subcontractor_payments');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['has_subcontractor_payments', 'has_project_manager_budgets']);
        });
    }
};
