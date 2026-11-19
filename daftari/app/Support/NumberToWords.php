<?php

namespace App\Support;

/**
 * Spells out a SAR amount for the "amount in words" line on printed
 * documents. Two independent formats live here side by side:
 *
 * - sar()/convert() — the original English-words format used by the
 *   "In Words" line on invoice/quotation/bill PDFs (bilingual_classic and
 *   custom_letterhead layouts), e.g. "Saudi Riyal Five Thousand, Three
 *   Hundred And Forty Seven and Fifty Halala only".
 * - arabicRiyals()/englishRiyals() — a bilingual pair for the Payment/
 *   Receipt Voucher redesign, matching the phrasing convention on printed
 *   Saudi cheques/vouchers, e.g. "One Thousand Five Hundred Saudi Riyals
 *   Only" / "ألف وخمسمائة ريال سعودي فقط لا غير". These deliberately keep
 *   "ريال سعودي" / "Saudi Riyals" grammatically invariant rather than
 *   inflecting it for the counted number (dual/plural noun agreement,
 *   3-10 vs 11-99 accusative forms, etc.) — Arabic numeral-noun agreement
 *   is real grammar, but every printed voucher convention this was
 *   modeled on already takes this same shortcut.
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

    private const ONES_AR = [
        1 => 'واحد', 2 => 'اثنان', 3 => 'ثلاثة', 4 => 'أربعة', 5 => 'خمسة',
        6 => 'ستة', 7 => 'سبعة', 8 => 'ثمانية', 9 => 'تسعة', 10 => 'عشرة',
        11 => 'أحد عشر', 12 => 'اثنا عشر', 13 => 'ثلاثة عشر', 14 => 'أربعة عشر', 15 => 'خمسة عشر',
        16 => 'ستة عشر', 17 => 'سبعة عشر', 18 => 'ثمانية عشر', 19 => 'تسعة عشر',
    ];

    private const TENS_AR = [2 => 'عشرون', 3 => 'ثلاثون', 4 => 'أربعون', 5 => 'خمسون', 6 => 'ستون', 7 => 'سبعون', 8 => 'ثمانون', 9 => 'تسعون'];

    private const HUNDREDS_AR = [1 => 'مائة', 2 => 'مائتان', 3 => 'ثلاثمائة', 4 => 'أربعمائة', 5 => 'خمسمائة', 6 => 'ستمائة', 7 => 'سبعمائة', 8 => 'ثمانمائة', 9 => 'تسعمائة'];

    private const SCALES_AR = [
        1 => ['singular' => 'ألف', 'dual' => 'ألفان', 'plural' => 'آلاف', 'accusative' => 'ألفاً'],
        2 => ['singular' => 'مليون', 'dual' => 'مليونان', 'plural' => 'ملايين', 'accusative' => 'مليوناً'],
        3 => ['singular' => 'مليار', 'dual' => 'ملياران', 'plural' => 'مليارات', 'accusative' => 'ملياراً'],
    ];

    private const ONES_EN = [
        1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
        10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen', 15 => 'Fifteen',
        16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
    ];

    private const TENS_EN = [2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty', 6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety'];

    private const SCALES_EN = [1 => 'Thousand', 2 => 'Million', 3 => 'Billion'];

    public static function arabicRiyals(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $riyals = (int) floor($amount);
        $halalas = (int) round(($amount - $riyals) * 100);

        $result = ($riyals === 0 && $halalas === 0 ? 'صفر' : self::convertWholeAr($riyals)).' ريال سعودي';

        if ($halalas > 0) {
            $result .= ' و'.self::convertGroupAr($halalas).' هللة';
        }

        return $result.' فقط لا غير';
    }

    public static function englishRiyals(float $amount): string
    {
        $amount = round(abs($amount), 2);
        $riyals = (int) floor($amount);
        $halalas = (int) round(($amount - $riyals) * 100);

        $result = ($riyals === 0 && $halalas === 0 ? 'Zero' : self::convertWholeEn($riyals)).' Saudi Riyals';

        if ($halalas > 0) {
            $result .= ' and '.self::convertGroupEn($halalas).' Halalas';
        }

        return $result.' Only';
    }

    private static function convertWholeAr(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        $groups = [];
        $n = $number;
        $level = 0;
        while ($n > 0) {
            $groups[$level] = $n % 1000;
            $n = intdiv($n, 1000);
            $level++;
        }

        $parts = [];
        foreach (array_reverse(array_keys($groups)) as $lvl) {
            $val = $groups[$lvl];
            if ($val === 0) {
                continue;
            }

            if ($lvl === 0) {
                $parts[] = self::convertGroupAr($val);

                continue;
            }

            $scale = self::SCALES_AR[$lvl];
            $parts[] = match (true) {
                $val === 1 => $scale['singular'],
                $val === 2 => $scale['dual'],
                $val <= 10 => self::convertGroupAr($val).' '.$scale['plural'],
                default => self::convertGroupAr($val).' '.$scale['accusative'],
            };
        }

        return implode(' و', $parts);
    }

    /** Converts 0-999 to Arabic words. */
    private static function convertGroupAr(int $n): string
    {
        $parts = [];

        if ($n >= 100) {
            $parts[] = self::HUNDREDS_AR[intdiv($n, 100)];
            $n %= 100;
        }

        if ($n > 0) {
            if ($n < 20) {
                $parts[] = self::ONES_AR[$n];
            } else {
                $ones = $n % 10;
                $tens = intdiv($n, 10);
                $parts[] = $ones > 0 ? self::ONES_AR[$ones].' و'.self::TENS_AR[$tens] : self::TENS_AR[$tens];
            }
        }

        return implode(' و', $parts);
    }

    private static function convertWholeEn(int $number): string
    {
        if ($number === 0) {
            return '';
        }

        $groups = [];
        $n = $number;
        $level = 0;
        while ($n > 0) {
            $groups[$level] = $n % 1000;
            $n = intdiv($n, 1000);
            $level++;
        }

        $parts = [];
        foreach (array_reverse(array_keys($groups)) as $lvl) {
            $val = $groups[$lvl];
            if ($val === 0) {
                continue;
            }

            $parts[] = $lvl === 0
                ? self::convertGroupEn($val)
                : self::convertGroupEn($val).' '.self::SCALES_EN[$lvl];
        }

        return implode(' ', $parts);
    }

    /** Converts 0-999 to English words. */
    private static function convertGroupEn(int $n): string
    {
        $parts = [];

        if ($n >= 100) {
            $parts[] = self::ONES_EN[intdiv($n, 100)].' Hundred';
            $n %= 100;
        }

        if ($n > 0) {
            if ($n < 20) {
                $parts[] = self::ONES_EN[$n];
            } else {
                $ones = $n % 10;
                $tens = intdiv($n, 10);
                $parts[] = $ones > 0 ? self::TENS_EN[$tens].'-'.self::ONES_EN[$ones] : self::TENS_EN[$tens];
            }
        }

        return implode(' ', $parts);
    }
}
