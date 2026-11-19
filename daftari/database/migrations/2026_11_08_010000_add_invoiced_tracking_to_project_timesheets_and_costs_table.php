<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5 — Billing: a Time & Material invoice bills real approved
 * timesheets and billable project costs. invoice_id/invoiced_at mark
 * which ones have already gone out on an invoice, so the next T&M
 * invoice only picks up what's genuinely still unbilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('project_timesheets', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('project_cost_id')->constrained()->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable()->after('invoice_id');
        });

        Schema::table('project_costs', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('created_by')->constrained()->nullOnDelete();
            $table->timestamp('invoiced_at')->nullable()->after('invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('project_timesheets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('invoiced_at');
        });

        Schema::table('project_costs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('invoiced_at');
        });
    }
};
