<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->string('table_header_color', 7)->nullable()->after('accent_color');
            $table->boolean('show_unit_labels')->default(true)->after('show_logo');
            $table->boolean('show_party_vat_number')->default(true)->after('show_unit_labels');
            $table->string('page_size', 10)->default('a4')->after('show_party_vat_number');
            $table->text('terms_en')->nullable()->after('notes_ar');
            $table->text('terms_ar')->nullable()->after('terms_en');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn(['table_header_color', 'show_unit_labels', 'show_party_vat_number', 'page_size', 'terms_en', 'terms_ar']);
        });
    }
};
