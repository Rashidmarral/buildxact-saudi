<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Bug report: "i have set invoice layout different and quotation layout
 * different i don't have option to change quotation layout to new
 * template ... user choose the layout type and then user have multiple
 * choice selection where it is applies and also user have option to
 * deselect." Each InvoiceTemplate row has always been scoped to exactly
 * one document_type — applying one design to several document types
 * meant manually recreating it once per type, with no way to see or
 * manage those as "the same design" afterward. design_group ties
 * together the set of rows (one per applicable document type) that
 * represent one design, without changing what any row itself means or
 * touching Company::defaultTemplateFor() or any print-path query —
 * those keep working exactly as before, one row per document_type.
 *
 * Also adds the first round of requested "rich design" controls beyond
 * color/density: font_family (browser view only — see MpdfRenderer's own
 * comment on why the PDF path stays on the one embedded, Arabic-shaping-
 * verified font), font_size (a base text-size adjustment), and
 * border_style (an outer frame around the whole document).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->uuid('design_group')->nullable()->after('company_id');
            $table->string('font_family', 30)->nullable()->after('layout');
            $table->string('font_size', 10)->nullable()->after('font_family');
            $table->string('border_style', 10)->nullable()->after('font_size');

            $table->index(['company_id', 'design_group']);
        });

        // Every pre-existing row becomes its own standalone, one-member
        // design group — it already applies to exactly the one
        // document_type it always had, so nothing about how it behaves
        // changes; this only backfills the handle the new editor needs
        // to treat it as "a design."
        DB::table('invoice_templates')->whereNull('design_group')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $row) {
                DB::table('invoice_templates')->where('id', $row->id)->update(['design_group' => (string) Str::uuid()]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn(['design_group', 'font_family', 'font_size', 'border_style']);
        });
    }
};
