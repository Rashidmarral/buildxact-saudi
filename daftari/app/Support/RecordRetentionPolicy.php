<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Saudi VAT Implementing Regulations Article 66: accounting books and
 * records (invoices, credit/debit notes, and the underlying ledger
 * entries behind them — expenses, income, customs declarations, ...)
 * must be kept for at least 6 years from the end of the tax period they
 * belong to. Nothing in the app enforced this at all before — once a
 * document was finalized (approved/posted/issued, not a draft that was
 * never really part of the books), the only thing standing between it
 * and a delete button was whatever document-specific guard that
 * controller happened to already have (ZATCA-lock, draft-only, ...),
 * none of which know anything about the retention requirement itself.
 *
 * Deliberately a single, tiny, document-agnostic check rather than a
 * per-model column or scope — every caller already knows the one date
 * that matters for its own document (issue date, expense date, ...);
 * this just answers "is that date still inside the mandatory window?".
 * A date past the window isn't force-deleted or flagged for cleanup —
 * retention only ever blocks a delete that would otherwise happen; it
 * never requires one.
 *
 * Audit finding COMP-01: the window used to count 6 years from the
 * document's own date directly, not from the end of its tax period as
 * the regulation actually requires — a document dated early in a
 * quarter became legally deletable up to ~3 months before Article 66
 * actually permits it. Counting from the end of the QUARTER containing
 * the document's date (via the same VatPeriods helper the VAT return
 * tracker already uses) fixes this for a company that files quarterly,
 * and is also a safe, deliberately conservative over-approximation for
 * one that files monthly — a quarter always ends on or after the month
 * containing the same date, so this never retains for LESS time than
 * Article 66 requires, regardless of which frequency a given company
 * actually files under (a fact this document-agnostic check has no way
 * to know without every caller threading its own company through).
 */
class RecordRetentionPolicy
{
    public const RETENTION_YEARS = 6;

    public static function isWithinRetentionWindow(CarbonInterface|\DateTimeInterface $date): bool
    {
        $date = $date instanceof CarbonInterface ? $date : \Illuminate\Support\Carbon::instance($date);

        $periodEnd = VatPeriods::periodContaining('quarterly', $date)['end'];

        return $periodEnd->diffInYears(now(), true) < self::RETENTION_YEARS;
    }
}
