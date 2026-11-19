<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "the last voucher 6000 is against same project but different work
 * like MC1 and RC2 spraying... we have an option to add subproject
 * option or work for main project and assign voucher accordingly" —
 * a budget (mainly a Project Manager Budget, though nothing stops a
 * Subcontract from using it too) can cover more than one distinct
 * piece of work on the same project. A Work is a named slice of that
 * budget — optionally with its own budget_amount to track against,
 * same idea as a Subcontract's milestones but without the percentage-
 * of-contract-value/handover-approval machinery a contractual
 * milestone schedule needs — a payment just gets tagged to one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontract_works', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcontract_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('budget_amount', 14, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontract_works');
    }
};
