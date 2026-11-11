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

    // Bounded on both sides so a reference code like "REF123" doesn't read
    // its trailing digits as a standalone amount. The decimal suffix is
    // mandatory, not optional: every genuine amount or running balance on
    // a real bank statement is shown with exactly 2 decimal places, even a
    // whole-currency-unit figure ("500.00", not "500") — requiring it is a
    // safe, currency-specific disambiguator against a bare short integer
    // that's actually a stray fragment of a reference/ID number split
    // across lines by PDF text extraction (e.g. a 3-digit tail landing on
    // its own line), which would otherwise be misread as a tiny real
    // amount and silently corrupt the import with a wrong figure instead
    // of just skipping an unparseable line.
    private const AMOUNT_PATTERN = '/(?<![A-Za-z0-9])-?\d{1,3}(?:,\d{3})*\.\d{1,2}(?![A-Za-z0-9])/';

    // Safety bound on how many extra lines a single transaction's
    // description is allowed to fold in — see the loop below — so a
    // genuinely unparseable row can never cascade into swallowing
    // unrelated content for the rest of the file.
    private const MAX_CONTINUATION_LINES = 12;

    /**
     * @return array<int, array{date: string, description: string, reference: null, amount: string}>
     */
    public static function parse(UploadedFile $file): array
    {
        $text = (new PdfTextParser)->parseFile($file->getRealPath())->getText();
        $lines = array_values(preg_split('/\r\n|\r|\n/', $text));
        $count = count($lines);

        $rows = [];
        $i = 0;

        while ($i < $count) {
            $line = trim(preg_replace('/\s+/', ' ', $lines[$i]));
            $i++;

            if ($line === '' || ! preg_match(self::DATE_PATTERN, $line, $dateMatch)) {
                continue;
            }

            $date = $dateMatch[1];
            $firstSegment = trim(substr($line, strlen($dateMatch[1])));
            $segments = $firstSegment === '' ? [] : [$firstSegment];
            $consumed = 0;

            // A transaction's description routinely wraps across several
            // physical lines in the extracted text (a table cell that
            // visually spans multiple lines becomes several separate text
            // lines once extracted) — and when a row straddles a page
            // break, the date and the rest of the row can even come out
            // of order entirely, with page header/footer boilerplate
            // interleaved. Keep folding in one line at a time, checking
            // only the newest one for a resolvable amount (never the
            // whole buffer — see amountCandidates()), until one is
            // found, the next transaction already looks like it's
            // starting (another date-leading line), or the safety bound
            // above is hit.
            $amount = null;
            $terminalIndex = null;
            $amountMatches = [];

            while (true) {
                $lastIndex = count($segments) - 1;

                if ($lastIndex >= 0) {
                    $candidates = self::amountCandidates($segments[$lastIndex]);

                    if ($candidates !== []) {
                        $resolved = self::resolveAmount($segments[$lastIndex], $candidates);

                        if ($resolved !== null) {
                            $amount = $resolved;
                            $terminalIndex = $lastIndex;
                            $amountMatches = $candidates;
                            break;
                        }
                    }
                }

                if ($i >= $count || $consumed >= self::MAX_CONTINUATION_LINES) {
                    break;
                }

                $next = trim(preg_replace('/\s+/', ' ', $lines[$i]));

                if ($next !== '' && preg_match(self::DATE_PATTERN, $next)) {
                    break;
                }

                $i++;
                $consumed++;

                if ($next !== '') {
                    $segments[] = $next;
                }
            }

            if ($amount === null) {
                continue;
            }

            $firstAmountPos = strpos($segments[$terminalIndex], $amountMatches[0]);
            $description = trim(
                implode(' ', array_slice($segments, 0, $terminalIndex)).' '.
                substr($segments[$terminalIndex], 0, $firstAmountPos)
            );

            if ($description === '') {
                continue;
            }

            $rows[] = [
                'date' => self::normalizeDate($date),
                'description' => $description,
                'reference' => null,
                'amount' => $amount,
            ];
        }

        return $rows;
    }

    /**
     * Every AMOUNT_PATTERN match on a line, excluding one immediately
     * preceded by a "Charges:"-style inline label. A wrapped transfer
     * description routinely mentions its own incidental bank-charge
     * figure that way (e.g. "...20261028SANCBKNCBK6B8241182 5594424
     * Charges: 1.00 REMBK:THE SAUDI NATIONAL BANK..."), well before the
     * row's actual reference+type+amount+balance line — reading it as
     * this row's amount would silently record the 1 SAR fee instead of
     * the real transaction value.
     */
    private static function amountCandidates(string $line): array
    {
        if (! preg_match_all(self::AMOUNT_PATTERN, $line, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $kept = [];

        foreach ($matches[0] as [$value, $offset]) {
            if (! preg_match('/charges\s*:\s*$/i', substr($line, 0, $offset))) {
                $kept[] = $value;
            }
        }

        return $kept;
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
