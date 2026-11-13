<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One labeled slice of an AdvanceCredit — "Subgrade advance," "Aggregate
 * base advance," "Asphalt/MC1 advance," each carrying its own VAT-exclusive
 * amount/VAT/total, matching how a real advance invoice usually allocates
 * one lump-sum payment across several future scopes of work up front.
 * How much of a portion is still unapplied is computed from its
 * AdvanceCreditApplication rows (see that migration), not stored here —
 * same pattern as CashTracker::balance().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advance_credit_portions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('advance_credit_id')->constrained()->cascadeOnDelete();
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(15);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'advance_credit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advance_credit_portions');
    }
};
