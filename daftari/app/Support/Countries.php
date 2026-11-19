<?php

namespace App\Support;

/**
 * Shared country list — used by the Platform Settings "Default country"
 * picker and, from there, as the pre-filled default on new
 * client/supplier/branch "Country" fields. A single source so both stay
 * in sync.
 */
class Countries
{
    public const LIST = [
        'SA' => 'Saudi Arabia', 'AE' => 'United Arab Emirates', 'KW' => 'Kuwait', 'QA' => 'Qatar',
        'BH' => 'Bahrain', 'OM' => 'Oman', 'EG' => 'Egypt', 'JO' => 'Jordan', 'LB' => 'Lebanon',
        'IQ' => 'Iraq', 'YE' => 'Yemen', 'US' => 'United States', 'GB' => 'United Kingdom',
        'DE' => 'Germany', 'FR' => 'France', 'IN' => 'India', 'PK' => 'Pakistan',
    ];

    public static function name(?string $code): ?string
    {
        return $code ? (self::LIST[$code] ?? null) : null;
    }
}
