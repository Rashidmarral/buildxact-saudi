<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Material issued from real inventory stock to a project. Reduces the
 * item's on-hand quantity (so stock reports stay accurate) and posts a
 * ProjectCost row (cost_head 'materials') for project margin purposes.
 * Deliberately does NOT post its own GL/journal entry for the stock
 * value change — doing that correctly (a dedicated job-cost/COGS
 * account distinct from StockAdjustment's generic inventory-adjustment
 * account) is a larger piece of work the user hasn't asked for yet;
 * this phase tracks project cost/margin only, same scope boundary as
 * the equipment and timesheet costs in this same migration batch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_material_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->nullOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('total', 14, 2);
            $table->string('issued_to', 255)->nullable();
            $table->date('issued_at');
            $table->foreignId('project_cost_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_material_issues');
    }
};
