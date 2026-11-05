<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ZATCA compliance gap: an advance/deposit collected before the main
 * supply must be issued as its own formal "prepayment tax invoice"
 * (InvoiceTypeCode 386) — VAT is due on the advance at the moment it's
 * received, not deferred to whenever the final invoice is issued. The
 * final invoice then references this document (number/date/time) and
 * reports it in cbc:PrepaidAmount so ZATCA doesn't see the VAT counted
 * twice — see ZatcaXmlGenerator::generateForPrepayment() and the
 * $appliedPrepayment handling in generate().
 *
 * Deliberately NOT itemized like Invoice/CreditNote/DebitNote — a real
 * prepayment invoice is a single advance amount against a client or
 * project, not a priced breakdown of goods/services (those aren't known
 * yet, which is the whole reason it's an advance). tax_rate_id mirrors
 * how every other document type here already resolves its ZATCA tax
 * category (standard/zero-rated/exempt/not-subject) and exemption
 * reason, instead of re-deriving that from a bare percentage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prepayment_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('tax_rate_id')->nullable()->constrained()->nullOnDelete();
            $table->string('prepayment_number');
            $table->string('type', 20)->default('standard'); // standard (B2B, clearance), simplified (B2C, reporting)
            $table->string('status', 20)->default('draft'); // draft, issued, cancelled
            $table->date('issue_date');
            $table->string('description')->nullable(); // what the advance is for, e.g. "Advance against PO-0012"
            $table->decimal('amount', 12, 2)->default(0); // tax-exclusive advance amount
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0); // tax-inclusive — what ZATCA's PrepaidAmount on the final invoice must equal
            $table->string('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->text('qr_code')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'prepayment_number']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prepayment_invoices');
    }
};
