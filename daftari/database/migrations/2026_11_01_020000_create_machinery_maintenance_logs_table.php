<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One real service event per row — what was done, when, and (if known)
 * the real Expense that paid for it. Logging an event can also set the
 * machine's next_maintenance_due_date (machinery_assets), which is
 * what actually drives the reminder; this table is the history behind
 * that date, not a second source of truth for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machinery_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('machinery_asset_id')->constrained()->cascadeOnDelete();
            $table->string('service_type', 100);
            $table->date('performed_at');
            $table->foreignId('expense_id')->nullable()->constrained()->nullOnDelete();
            $table->date('next_due_date')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'machinery_asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machinery_maintenance_logs');
    }
};
