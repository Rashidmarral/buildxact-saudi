<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            // bilingual, english_only, arabic_only
            $table->string('language_mode', 20)->default('bilingual')->after('layout');
            // ltr, rtl
            $table->string('table_direction', 10)->default('ltr')->after('language_mode');
            $table->boolean('show_signature')->default(false)->after('table_direction');
            $table->string('signature_label_en')->nullable()->after('show_signature');
            $table->string('signature_label_ar')->nullable()->after('signature_label_en');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn(['language_mode', 'table_direction', 'show_signature', 'signature_label_en', 'signature_label_ar']);
        });
    }
};
