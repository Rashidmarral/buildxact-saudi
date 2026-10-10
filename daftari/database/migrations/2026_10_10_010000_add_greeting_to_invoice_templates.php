<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            // Lets a company replace (or hide) the "Dear Sirs, We are
            // pleased to submit the following quotation..." boilerplate
            // the quotation_offer layout otherwise prints on every
            // document. Null greeting_en/greeting_ar falls back to that
            // default text.
            $table->boolean('show_greeting')->default(true)->after('signature_label_ar');
            $table->text('greeting_en')->nullable()->after('show_greeting');
            $table->text('greeting_ar')->nullable()->after('greeting_en');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_templates', function (Blueprint $table) {
            $table->dropColumn(['show_greeting', 'greeting_en', 'greeting_ar']);
        });
    }
};
