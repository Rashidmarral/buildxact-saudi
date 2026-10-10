<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_chain_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // 'purchase_order' | 'expense' today; the polymorphic progress
            // table below already generalizes to any future document type.
            $table->string('document_type', 30);
            $table->unsignedTinyInteger('step_number');
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            // The amount at/above which this step is required — mirrors
            // the existing company.*_approval_threshold comparison
            // (">="), so a document only picks up the tiers its total
            // actually reaches (e.g. a small PO skips the GM step).
            $table->decimal('min_amount', 12, 2);
            $table->timestamps();

            $table->unique(['company_id', 'document_type', 'step_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_chain_steps');
    }
};
