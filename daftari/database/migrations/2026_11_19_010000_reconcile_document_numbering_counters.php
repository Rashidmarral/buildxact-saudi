<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * QA follow-up finding: pre-fix software (before NUM-05/QA-07 existed)
 * could rewind a company's shared next_*_number counter below a number
 * already issued on a real document — e.g. resetting it to 1 the moment
 * a yearly-numbering toggle flipped. The monotonic nextSequenceNumber()
 * logic added by QA-07 can never create a NEW collision going forward,
 * but it has no way to detect that its OWN starting point was already
 * corrupted by that older bug. This migration closes that one historical
 * gap by raising each company's counter above the highest number it has
 * ever actually issued, parsed straight from the documents themselves —
 * no existing document is read-modified, only the forward-looking
 * companies.next_*_number columns are written, and only when the stored
 * counter is found to be at or below a number already in use.
 *
 * Streams every table with lazy() (the QA-09 lesson) so this stays
 * memory-safe at real volume. On a large existing dataset this is a
 * full scan of every invoice/credit-note/debit-note/prepayment/
 * quotation/proforma row the installation has ever created — expect it
 * to take real time proportional to that history, same caution as the
 * party_snapshot backfill before it, and plan/test it before running
 * against a large live deployment.
 */
return new class extends Migration
{
    private const SERIES = [
        ['table' => 'invoices', 'number_col' => 'invoice_number', 'counter_col' => 'next_invoice_number'],
        ['table' => 'credit_notes', 'number_col' => 'credit_note_number', 'counter_col' => 'next_credit_note_number'],
        ['table' => 'debit_notes', 'number_col' => 'debit_note_number', 'counter_col' => 'next_debit_note_number'],
        ['table' => 'prepayment_invoices', 'number_col' => 'prepayment_number', 'counter_col' => 'next_prepayment_number'],
        ['table' => 'quotations', 'number_col' => 'quotation_number', 'counter_col' => 'next_quotation_number', 'type' => 'quotation'],
        ['table' => 'quotations', 'number_col' => 'quotation_number', 'counter_col' => 'next_proforma_number', 'type' => 'proforma'],
    ];

    public function up(): void
    {
        foreach (self::SERIES as $series) {
            $maxByCompany = $this->maxIssuedNumberByCompany($series);

            foreach ($maxByCompany as $companyId => $max) {
                DB::table('companies')
                    ->where('id', $companyId)
                    ->where($series['counter_col'], '<=', $max)
                    ->update([$series['counter_col'] => $max + 1]);
            }
        }
    }

    /**
     * Parses the trailing numeric segment (after the last "-") of every
     * document number in this series — correct for both the plain
     * "PREFIX-00001" and yearly "PREFIX-2026-00001" formats, and for any
     * digit width, not just the current 5-digit padding. Deliberately
     * includes soft-deleted rows (invoices/quotations use SoftDeletes):
     * a voided document's number must never be reissued either.
     */
    private function maxIssuedNumberByCompany(array $series): array
    {
        $maxByCompany = [];

        $query = DB::table($series['table'])->select('company_id', $series['number_col']);
        if (isset($series['type'])) {
            $query->where('type', $series['type']);
        }

        foreach ($query->orderBy('id')->lazy() as $row) {
            $number = $row->{$series['number_col']};
            if (! $number || ! preg_match('/-(\d+)$/', $number, $m)) {
                continue;
            }

            $value = (int) $m[1];
            $companyId = $row->company_id;
            if (! isset($maxByCompany[$companyId]) || $value > $maxByCompany[$companyId]) {
                $maxByCompany[$companyId] = $value;
            }
        }

        return $maxByCompany;
    }

    public function down(): void
    {
        // Deliberately irreversible: this migration only ever raises a
        // counter to stay above a number already issued. Reverting it
        // would reopen exactly the collision risk it exists to close,
        // so there is nothing safe to roll back to.
    }
};
