<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "i need to send my client a document with attach agreement p/o order
 * and quotations in a single pdf document" — lets a letter/agreement
 * reference existing Quotations and Purchase Orders already in the
 * system (the "PO document reference" / "Referenced quotation" fields
 * a real bilingual agreement typically carries), and — unlike the
 * existing free-file attachments() relation, which only stores a
 * scanned copy someone uploaded — CompanyLetterController::pdf() folds
 * each linked document's own live-rendered PDF into the letter's
 * combined download via MpdfRenderer::mergePdfs(), the exact mechanism
 * PurchaseOrderController::downloadPdf() already uses for its own PDF
 * attachments. Polymorphic (linkable_type/linkable_id) rather than two
 * separate nullable FK columns so a future linkable document type
 * (an Invoice, a Bill, ...) is a model/UI change, not a migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_letter_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_letter_id')->constrained()->cascadeOnDelete();
            $table->string('linkable_type');
            $table->unsignedBigInteger('linkable_id');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['linkable_type', 'linkable_id']);
            $table->unique(['company_letter_id', 'linkable_type', 'linkable_id'], 'company_letter_links_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_letter_links');
    }
};
