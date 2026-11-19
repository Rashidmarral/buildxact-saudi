<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // A structured, bilingual Terms & Conditions page — separate
            // from the plain `notes` field (max 2000 chars, one language,
            // no section structure), which couldn't fit a real multi-
            // section bilingual terms document a user tried pasting in.
            // JSON array of {title_en, title_ar, paragraphs: [{heading_en,
            // heading_ar, body_en, body_ar}, ...]} — see Quotation::
            // pdfData() and documents.print.pdf-terms-and-conditions for
            // how it's rendered.
            $table->json('terms_sections')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn('terms_sections');
        });
    }
};
