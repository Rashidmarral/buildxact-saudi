<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            // 'compact' matches the tightened default spacing shipped in
            // documents/print/pdf.blade.php (fits a short 2-3 line invoice
            // on one page); 'comfortable' restores the roomier spacing for
            // companies that don't mind an extra page.
            $table->string('density', 20)->default('compact')->after('layout');
            $table->string('totals_color', 7)->nullable()->after('table_header_color');
            $table->boolean('show_vat_column')->default(true)->after('show_item_description');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn(['density', 'totals_color', 'show_vat_column']);
        });
    }
};
