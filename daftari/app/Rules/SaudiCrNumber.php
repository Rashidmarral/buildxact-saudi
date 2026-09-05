<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A Saudi Commercial Registration number is 10 digits. Also rejects the
 * obvious placeholder patterns (all the same digit, a plain sequential
 * run) so a company can't accidentally go live with fake data.
 */
class SaudiCrNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = preg_replace('/\s+/', '', (string) $value);

        if (! preg_match('/^\d{10}$/', $digits)) {
            $fail(__('The :attribute must be exactly 10 digits, matching your Commercial Registration certificate.'));

            return;
        }

        if ($this->looksLikePlaceholder($digits)) {
            $fail(__('The :attribute looks like a placeholder value. Please enter your company\'s real CR number from your Commercial Registration certificate.'));
        }
    }

    private function looksLikePlaceholder(string $digits): bool
    {
        if (preg_match('/^(\d)\1{9}$/', $digits)) {
            return true;
        }

        return $this->isConsecutiveRun($digits);
    }

    /**
     * True if every digit is exactly one more (or one less), mod 10, than
     * the one before it — catches any rotation of a sequential placeholder
     * (0123456789, 1234567890, 9876543210, ...), not just the one starting
     * at 0.
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
