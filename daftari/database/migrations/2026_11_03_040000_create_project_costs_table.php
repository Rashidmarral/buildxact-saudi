<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The project's own cost ledger — distinct from Expense/Bill (which
 * already feed Project::costs() directly and keep showing on the
 * Overview tab's "Linked bills"/"Linked expenses" lists). This table is
 * for costs that have nowhere else to live: a manual "Add cost" entry,
 * and — from Phase 2 onward — machinery usage, timesheets and material
 * issues logged against this project, each classified under one of
 * Project::COST_HEADS so the Budget tab can compare budgeted vs actual
 * per cost head. Project::costs() sums this table alongside
 * Expense/Bill so nothing posted here is invisible to project margin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('cost_head', 30);
            $table->date('cost_date');
            $table->string('description', 255);
            $table->decimal('quantity', 14, 2)->nullable();
            $table->decimal('unit_cost', 14, 2)->nullable();
            $table->decimal('amount', 14, 2);
            $table->boolean('billable')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'cost_head']);
            $table->index(['project_id', 'cost_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_costs');
    }
};
