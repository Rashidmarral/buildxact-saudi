<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QA audit finding: next_invoice_number (and the same pattern for credit
 * notes, debit notes, prepayments, proformas, and quotations) is one
 * column shared between lifetime and yearly numbering. Turning yearly
 * numbering on reset that shared column down to 1 the moment the stored
 * fiscal year label didn't match today's — discarding whatever lifetime
 * count it already held — so turning it back off handed out a number
 * that collided with one already on a real document.
 *
 * This baseline column lets Company::nextSequenceNumber() stop ever
 * rewinding the underlying counter at all: it records where the counter
 * stood the moment each new fiscal year was first encountered, and the
 * yearly-mode number shown to the user is simply (counter - baseline).
 * The counter itself only ever climbs, in either mode, so switching
 * modes back and forth can never hand out a number twice.
 */
return new class extends Migration
{
    private const SERIES = [
        'invoice_number_fiscal_year',
        'credit_note_number_fiscal_year',
        'debit_note_number_fiscal_year',
        'prepayment_number_fiscal_year',
        'proforma_number_fiscal_year',
        'quotation_number_fiscal_year',
    ];

    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            foreach (self::SERIES as $fiscalYearColumn) {
                $table->unsignedInteger($fiscalYearColumn.'_baseline')->default(0)->after($fiscalYearColumn);
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(array_map(fn ($c) => $c.'_baseline', self::SERIES));
        });
    }
};
