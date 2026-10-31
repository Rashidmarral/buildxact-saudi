<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "i need to add a module to handle subcontractor payments... this is
 * not for one subcontractor in future i can give some another
 * subcontractor a project to track there payments" — a Subcontract is
 * one Supplier's agreed scope on one Project: a contract value, a
 * retention percentage held back until handover, and (via
 * subcontract_milestones) a payment schedule. Many subcontracts can
 * exist per project (different trades) and per supplier (different
 * projects), which is why this is its own join entity rather than a
 * field bolted onto Project or Supplier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subcontracts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('contract_number')->nullable();
            $table->string('title')->nullable();
            $table->decimal('contract_value', 14, 2)->default(0);
            $table->decimal('retention_percent', 5, 2)->default(5);
            $table->date('start_date')->nullable();
            $table->string('status', 20)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'project_id']);
            $table->index(['company_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subcontracts');
    }
};
