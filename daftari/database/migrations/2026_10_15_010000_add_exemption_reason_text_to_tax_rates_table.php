<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            // ZATCA's VAT category O ("Not subject to VAT", VATEX-SA-OOS)
            // has no fixed reason text list like E/Z — the spec says the
            // reason "is free text, to be provided by the taxpayer on
            // case to case basis" (section 11.2.4). Only used for that
            // type; Zero-rated/Exempt keep using the fixed VATEX-SA-* text
            // looked up from exemption_reason_code.
            $table->text('exemption_reason_text')->nullable()->after('exemption_reason_code');
        });
    }

    public function down(): void
    {
        Schema::table('tax_rates', function (Blueprint $table) {
            $table->dropColumn('exemption_reason_text');
        });
    }
};
