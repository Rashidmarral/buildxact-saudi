<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A company-uploadable background watermark image (a faded logo, a "DRAFT"
 * / company seal graphic, etc.) shown behind the document's own content —
 * distinct from the letterhead (replaces the header) and the footer
 * (a banner below the content): the watermark sits under everything, at a
 * configurable opacity so the printed text stays legible over it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->string('watermark_path')->nullable()->after('footer_path');
            $table->unsignedTinyInteger('watermark_opacity')->default(10)->after('watermark_path');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn(['watermark_path', 'watermark_opacity']);
        });
    }
};
