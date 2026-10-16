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
 */
class RecordRetentionPolicy
{
    public const RETENTION_YEARS = 6;

    public static function isWithinRetentionWindow(CarbonInterface|\DateTimeInterface $date): bool
    {
        $date = $date instanceof CarbonInterface ? $date : \Illuminate\Support\Carbon::instance($date);

        return $date->diffInYears(now(), true) < self::RETENTION_YEARS;
    }
}
