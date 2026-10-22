<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One draw-down of an AdvanceCreditPortion against a specific invoice —
 * the operator's own reconciliation ("Less: subgrade advance—INV-00001 ...
 * (20,700.00)") is exactly one of these rows. Partial application is
 * allowed on purpose: the amount applied here can be less than the
 * portion's own total, leaving a remainder available for a later
 * invoice (the operator's asphalt/MC1 portion stays untouched now and
 * gets applied in full, or in further parts, whenever that work is
 * actually invoiced). invoice_item_id is optional — ties the deduction
 * to one specific line on the invoice it's applied to, when the
 * operator wants that level of detail; null means it's a document-level
 * deduction only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_credit_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advance_credit_portion_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Explicit short names — the auto-generated name for the first
            // index (74 chars: table + both column names + "_index")
            // exceeds MySQL's 64-character identifier limit.
            $table->index(['company_id', 'advance_credit_portion_id'], 'aca_company_portion_idx');
            $table->index(['company_id', 'invoice_id'], 'aca_company_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_credit_applications');
    }
};
