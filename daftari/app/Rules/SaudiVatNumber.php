<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A real Saudi VAT registration number is exactly 15 digits, and by ZATCA's
 * own numbering convention always starts and ends with "3". This also
 * rejects the most common placeholder patterns people paste in while
 * testing (all the same digit, or a plain run like 123456789012345) so a
 * company can't accidentally go live with fake data still in the field.
 */
class SaudiVatNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\s+/', '', (string) $value);

        if (! preg_match('/^\d{15}$/', $digits)) {
            $fail(__('The :attribute must be exactly 15 digits.'));

            return;
        }

        if ($digits[0] !== '3' || $digits[14] !== '3') {
            $fail(__('The :attribute must start and end with 3, matching ZATCA\'s VAT number format (e.g. 3XXXXXXXXXXXXX3).'));

            return;
        }

        if ($this->looksLikePlaceholder($digits)) {
            $fail(__('The :attribute looks like a placeholder value. Please enter your company\'s real VAT registration number from the ZATCA/Fatoora portal.'));
        }
    }

    private function looksLikePlaceholder(string $digits): bool
    {
        // All 15 digits identical (e.g. 333333333333333).
        if (preg_match('/^(\d)\1{14}$/', $digits)) {
            return true;
        }

        // The 13 digits between the fixed leading/trailing "3" are a plain
        // sequential run (any rotation, ascending or descending — e.g.
        // 312345678901233, 313456789012303, 313210987654323, ...).
        return $this->isConsecutiveRun(substr($digits, 1, 13));
    }

    /**
     * True if every digit is exactly one more (or one less), mod 10, than
     * the one before it.
     */
    private function isConsecutiveRun(string $digits): bool
    {
        $ascending = true;
        $descending = true;

        for ($i = 1; $i < strlen($digits); $i++) {
            $prev = (int) $digits[$i - 1];
            $curr = (int) $digits[$i];

            if ($curr !== ($prev + 1) % 10) {
                $ascending = false;
            }
            if ($curr !== ($prev + 9) % 10) {
                $descending = false;
            }
        }

        return $ascending || $descending;
    }
}
