<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a final invoice declare which prepayment (advance) tax invoice
 * it settles — ZatcaXmlGenerator::generate() then reports that
 * prepayment's tax-inclusive total as cbc:PrepaidAmount and reduces
 * cbc:PayableAmount by the same, and adds the BR-KSA-73/80 document
 * reference back to it, so ZATCA doesn't see the VAT on that advance
 * counted twice. Deliberately does not touch this invoice's own
 * `total`/`amount_paid` — those keep reflecting what the business
 * actually invoiced/received, exactly as before; this column only
 * feeds ZATCA's own payable-amount bookkeeping in the XML.
 *
 * One prepayment invoice can be applied to at most one final invoice —
 * enforced in PrepaymentInvoiceController/InvoiceController, not here,
 * since "already applied elsewhere" is a business rule, not a schema
 * constraint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('applies_prepayment_invoice_id')->nullable()->after('quotation_id')->constrained('prepayment_invoices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('applies_prepayment_invoice_id');
        });
    }
};
