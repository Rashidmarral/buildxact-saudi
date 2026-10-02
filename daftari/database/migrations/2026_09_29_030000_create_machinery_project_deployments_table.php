<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A machine deployed on the company's OWN project — no client, no
 * invoice. internal_daily_rate is a purely notional costing figure shown
 * only on the machine's own statement; it deliberately never feeds
 * Project::costs()/cashPaid() or the Project Cash Flow ledger, since the
 * real cash cost of running the machine is whatever fuel/maintenance/
 * operator-wage Expense rows are tagged to this project (see
 * expenses.machinery_asset_id) — summing both would double the same cost,
 * the exact bug already fixed once for bank-to-cash transfers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_project_deployments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('internal_daily_rate', 12, 2)->nullable();
            $table->foreignId('operator_employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('status', 20)->default('active'); // active, completed
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_project_deployments');
    }
};
