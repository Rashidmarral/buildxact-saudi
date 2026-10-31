<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser as PdfTextParser;

/**
 * Best-effort line extraction from a bank-issued statement PDF — there's
 * no single standard layout (every bank lays out its date/description/
 * debit/credit/balance columns differently, and PDF text extraction loses
 * the original table structure, leaving just a left-to-right stream of
 * tokens per line), so this can't be as reliable as the existing clean
 * CSV import. It's deliberately conservative: a line only becomes a
 * candidate row if it starts with something that parses as a date AND has
 * at least one trailing number that looks like an amount; everything else
 * (headers, footers, page totals, disclaimers) is silently skipped rather
 * than guessed at. The caller is expected to let the user review what
 * came out (BankReconciliationController keeps every imported line
 * removable individually for exactly this reason) rather than trust it
 * blindly the way a clean CSV's rows are trusted.
 */
class BankStatementPdfParser
{
    private const DATE_PATTERN = '/^(\d{4}-\d{1,2}-\d{1,2}|\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}|\d{1,2}\s+[A-Za-z]{3,9}\s+\d{2,4})/';

    // Bounded on both sides so a reference code like "REF123" doesn't
    // read its trailing digits as a standalone amount.
    private const AMOUNT_PATTERN = '/(?<![A-Za-z0-9])-?\d{1,3}(?:,\d{3})*(?:\.\d{1,2})?(?![A-Za-z0-9])/';

    /**
     * @return array<int, array{date: string, description: string, reference: null, amount: string}>
     */
    public static function parse(UploadedFile $file): array
    {
        $text = (new PdfTextParser)->parseFile($file->getRealPath())->getText();

        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line));

            if ($line === '' || ! preg_match(self::DATE_PATTERN, $line, $dateMatch)) {
                continue;
            }

            $rest = trim(substr($line, strlen($dateMatch[1])));

            if (! preg_match_all(self::AMOUNT_PATTERN, $rest, $amountMatches)) {
                continue;
            }

            $amount = self::resolveAmount($rest, $amountMatches[0]);

            if ($amount === null) {
                continue;
            }

            $firstAmountPos = strpos($rest, $amountMatches[0][0]);
            $description = trim(substr($rest, 0, $firstAmountPos));

            if ($description === '') {
                continue;
            }

            $rows[] = [
                'date' => self::normalizeDate($dateMatch[1]),
                'description' => $description,
                'reference' => null,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * Debit/credit/balance column conventions vary by bank, so this picks
     * the most defensible reading of what's on the line rather than one
     * fixed column order. An explicit "DR"/"Debit" or "CR"/"Credit" label
     * on the line always wins when exactly one of the two is present —
     * some banks print an unsigned amount plus that label instead of a
     * minus sign. Otherwise:
     *  - 1 number: it IS the (already signed) amount.
     *  - 2 numbers: amount, then a running balance — the amount is first.
     *  - 3+ numbers: debit, credit, balance (plus anything else ignored) —
     *    whichever of the first two is non-zero is the real movement,
     *    debit read as an outflow. A line with both non-zero, or neither,
     *    is too ambiguous to guess at and is skipped.
     */
    private static function resolveAmount(string $rest, array $amounts): ?float
    {
        $clean = fn (string $a) => (float) str_replace(',', '', $a);
        $isDebit = (bool) preg_match('/\bDR\b|\bDebit\b/i', $rest);
        $isCredit = (bool) preg_match('/\bCR\b|\bCredit\b/i', $rest);
        $labeledSign = $isDebit xor $isCredit;

        if (count($amounts) === 1) {
            $amount = $clean($amounts[0]);

            return $labeledSign ? ($isDebit ? -abs($amount) : abs($amount)) : $amount;
        }

        if (count($amounts) === 2) {
            $amount = $clean($amounts[0]);

            return $labeledSign ? ($isDebit ? -abs($amount) : abs($amount)) : $amount;
        }

        $debit = $clean($amounts[0]);
        $credit = $clean($amounts[1]);

        if ($debit != 0 && $credit == 0) {
            return -abs($debit);
        }

        if ($credit != 0 && $debit == 0) {
            return abs($credit);
        }

        if ($labeledSign) {
            return $isDebit ? -abs($debit ?: $credit) : abs($credit ?: $debit);
        }

        return null;
    }

    /**
     * PHP's strtotime() silently assumes US month/day order for a
     * slash-or-dash date ("11/06/2026" → 6 Nov, not 11 Jun) — wrong for
     * every bank statement this parser will ever see, so dd/mm/yyyy is
     * read out explicitly rather than trusted to strtotime's guess. A
     * spelled-out month ("11 Jun 2026") has no such ambiguity and is left
     * to strtotime as before.
     */
    private static function normalizeDate(string $raw): string
    {
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{2,4})$#', $raw, $m)) {
            $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];

            return sprintf('%04d-%02d-%02d', $year, $m[2], $m[1]);
        }

        $timestamp = strtotime($raw);

        return $timestamp ? date('Y-m-d', $timestamp) : $raw;
    }
}
