<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // ZATCA requires quarterly VAT filing for most businesses;
            // monthly only applies once a company's taxable supplies
            // cross the revenue threshold ZATCA sets for large
            // taxpayers. Drives VatPeriods' quarter/month picker and due
            // dates, so VatReturnPeriodController can suggest the next
            // period instead of the user having to work out date ranges
            // by hand.
            $table->string('vat_filing_frequency', 20)->default('quarterly')->after('vat_recovery_percentage');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('vat_filing_frequency');
        });
    }
};
