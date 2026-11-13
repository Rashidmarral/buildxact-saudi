<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A project's Bill of Quantities — the itemized scope (item_no,
 * description, unit, quantity, rate) that a construction contract's
 * progress billing calculates against. rate is what the client is
 * charged per unit; cost_rate is this company's own internal cost per
 * unit (optional — used for a BOQ-level margin view). executed_quantity
 * is how much of the item is actually complete on site; billed_quantity
 * is how much of that has already been invoiced — Phase 5 (Billing)
 * reads both to compute what's billable now.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_boq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('item_no', 20)->nullable();
            $table->string('description', 500);
            $table->foreignId('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 14, 2);
            $table->decimal('rate', 14, 2);
            $table->decimal('cost_rate', 14, 2)->nullable();
            $table->decimal('executed_quantity', 14, 2)->default(0);
            $table->decimal('billed_quantity', 14, 2)->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_boq_items');
    }
};
