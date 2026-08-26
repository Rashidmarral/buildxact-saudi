<?php

namespace App\Support;

/**
 * The currencies invoices/bills can be denominated in. Kept to a short,
 * curated list (Gulf neighbours + the handful of currencies Saudi SMEs
 * actually trade in) rather than the full ISO 4217 table, since every
 * entry here needs a manually-entered exchange rate — there's no live FX
 * feed wired in, so a shorter list is a shorter list of rates to keep an
 * eye on.
 */
class Currencies
{
    public static function catalog(): array
    {
        return [
            'SAR' => 'Saudi Riyal (SAR)',
            'USD' => 'US Dollar (USD)',
            'EUR' => 'Euro (EUR)',
            'GBP' => 'British Pound (GBP)',
            'AED' => 'UAE Dirham (AED)',
            'KWD' => 'Kuwaiti Dinar (KWD)',
            'BHD' => 'Bahraini Dinar (BHD)',
            'QAR' => 'Qatari Riyal (QAR)',
            'OMR' => 'Omani Rial (OMR)',
            'EGP' => 'Egyptian Pound (EGP)',
            'JOD' => 'Jordanian Dinar (JOD)',
            'INR' => 'Indian Rupee (INR)',
        ];
    }

    public static function codes(): array
    {
        return array_keys(self::catalog());
    }
}
