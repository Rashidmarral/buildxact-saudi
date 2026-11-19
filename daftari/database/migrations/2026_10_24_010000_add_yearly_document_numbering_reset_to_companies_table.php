<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit finding NUM-05: invoice/credit-note/debit-note/prepayment/
 * quotation/proforma numbers are a single lifetime counter with no year
 * component and no reset logic. Opt-in (false by default — zero change
 * to any existing company's numbering until an owner turns it on in
 * Settings): when enabled, Company::nextInvoiceNumber() et al. embed the
 * current fiscal year in the formatted number and restart each sequence
 * at 1 the moment the fiscal year rolls over (Company::fiscal_year_start,
 * the same boundary already used by ResolvesReportPeriod). The *_fiscal_year
 * columns track, per sequence, which fiscal year its counter currently
 * represents — compared and reset inside the same locked transaction as
 * the increment itself, so two concurrent requests spanning a rollover
 * can't both reset and both hand out "00001".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('document_numbering_resets_yearly')->default(false)->after('fiscal_year_start');
            $table->string('invoice_number_fiscal_year', 4)->nullable()->after('next_invoice_number');
            $table->string('credit_note_number_fiscal_year', 4)->nullable()->after('next_credit_note_number');
            $table->string('debit_note_number_fiscal_year', 4)->nullable()->after('next_debit_note_number');
            $table->string('prepayment_number_fiscal_year', 4)->nullable()->after('next_prepayment_number');
            $table->string('quotation_number_fiscal_year', 4)->nullable()->after('next_quotation_number');
            $table->string('proforma_number_fiscal_year', 4)->nullable()->after('next_proforma_number');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'document_numbering_resets_yearly',
                'invoice_number_fiscal_year', 'credit_note_number_fiscal_year', 'debit_note_number_fiscal_year',
                'prepayment_number_fiscal_year', 'quotation_number_fiscal_year', 'proforma_number_fiscal_year',
            ]);
        });
    }
};
