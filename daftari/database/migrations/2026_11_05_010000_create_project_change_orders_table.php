<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A change to the project's agreed scope/value after the original
 * contract — Hamly's project Change Orders tab: title/amount (± to the
 * contract value)/cost impact/days impact/status. Approving one adjusts
 * the EFFECTIVE contract value (Project::effectiveContractValue()) —
 * the original contract_value itself is left untouched as the signed
 * reference figure. A change order's own supporting document goes on
 * the project's existing Documents tab (Attachment) rather than a
 * second per-change-order attachment mechanism.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_change_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('change_order_number', 30);
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->decimal('amount', 14, 2);
            $table->decimal('cost_impact', 14, 2)->nullable();
            $table->integer('days_impact')->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_change_orders');
    }
};
