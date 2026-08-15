<?php

namespace App\Support;

/**
 * Spells out a SAR amount in English words, e.g. 5347.50 ->
 * "Saudi Riyal Five Thousand, Three Hundred And Forty Seven and Fifty
 * Halala only" — matching the "amount in words" line common on Saudi
 * commercial quotations/invoices (halala = 1/100 riyal).
 */
class NumberToWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
        'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function sar(float $amount, string $currencyName = 'Saudi Riyal'): string
    {
        $amount = round($amount, 2);
        $riyals = (int) floor($amount);
        $halalas = (int) round(($amount - $riyals) * 100);

        $words = $currencyName.' '.self::convert($riyals);

        if ($halalas > 0) {
            $words .= ' and '.self::convert($halalas).' Halala';
        }

        return $words.' only';
    }

    public static function convert(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $groups = [];
        $scales = ['', 'Thousand', 'Million', 'Billion'];
        $scaleIndex = 0;

        while ($number > 0) {
            $chunk = $number % 1000;
            if ($chunk > 0) {
                $chunkWords = self::convertChunk($chunk);
                if ($scales[$scaleIndex] !== '') {
                    $chunkWords .= ' '.$scales[$scaleIndex];
                }
                array_unshift($groups, $chunkWords);
            }
            $number = (int) floor($number / 1000);
            $scaleIndex++;
        }

        return implode(', ', $groups);
    }

    private static function convertChunk(int $chunk): string
    {
        $parts = [];

        if ($chunk >= 100) {
            $parts[] = self::ONES[(int) floor($chunk / 100)].' Hundred';
            $chunk %= 100;
            if ($chunk > 0) {
                $parts[] = 'And';
            }
        }

        if ($chunk >= 20) {
            $tens = self::TENS[(int) floor($chunk / 10)];
            $ones = $chunk % 10;
            $parts[] = $ones > 0 ? $tens.' '.self::ONES[$ones] : $tens;
        } elseif ($chunk > 0) {
            $parts[] = self::ONES[$chunk];
        }

        return implode(' ', $parts);
    }
}
