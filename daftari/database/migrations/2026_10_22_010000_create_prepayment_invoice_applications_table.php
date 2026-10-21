<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a genuine ZATCA 386 Prepayment Invoice be drawn down against
 * several future invoices, in parts, instead of only ever being applied
 * once in full via invoices.applies_prepayment_invoice_id — e.g. a 20%
 * advance invoiced up front, then credited 30%+30% against two separate
 * completed-works invoices as the project progresses, with the remainder
 * still held in reserve. Each row still carries full ZATCA linkage: see
 * ZatcaXmlGenerator::generate()'s $appliedPrepayments handling, which
 * builds PrepaidAmount/the document reference from these rows exactly
 * the way it already does for the single-FK case. The older FK stays
 * supported unchanged for the simple, single-invoice, full-amount case —
 * this is an additional mechanism, not a replacement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepayment_invoice_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prepayment_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('vat_amount', 12, 2);
            $table->decimal('total', 12, 2);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'prepayment_invoice_id']);
            $table->index(['company_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepayment_invoice_applications');
    }
};
