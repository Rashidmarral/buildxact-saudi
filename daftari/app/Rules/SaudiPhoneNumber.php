<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts a Saudi mobile or landline number in any of the common written
 * forms (+966501234567, 00966501234567, 0501234567) and rejects the usual
 * placeholder patterns (all the same digit, a plain sequential run).
 */
class SaudiPhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $raw = trim((string) $value);
        $digits = preg_replace('/[^0-9]/', '', $raw);

        // Normalise to the trailing 9 significant digits (drop country
        // code "966"/"00966" or the leading trunk "0").
        if (str_starts_with($digits, '00966')) {
            $digits = substr($digits, 5);
        } elseif (str_starts_with($digits, '966')) {
            $digits = substr($digits, 3);
        } elseif (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (! preg_match('/^[1-9]\d{8}$/', $digits)) {
            $fail(__('The :attribute must be a valid Saudi phone number, e.g. 05XXXXXXXX or +9665XXXXXXXX.'));

            return;
        }

        if (preg_match('/^(\d)\1{8}$/', $digits)) {
            $fail(__('The :attribute looks like a placeholder value. Please enter a real contact number.'));
        }
    }
}
