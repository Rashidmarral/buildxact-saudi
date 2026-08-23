<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Daftari's own public-facing identity — name, logo, and legal/VAT
 * details — as shown on the marketing site footer and SaaS subscription
 * receipts. Editable at runtime by a super admin (stored in the Setting
 * key/value table); each key falls back to config('daftari.billing')
 * (env-configured) until an admin sets a value, so a fresh install
 * behaves exactly as before this page existed. VAT/CR/address/phone stay
 * null (never fabricated) until someone actually sets them.
 */
class PlatformBranding
{
    public static function all(): array
    {
        $billing = config('daftari.billing');

        return [
            'name' => Setting::get('platform_name', $billing['company_name']),
            'name_ar' => Setting::get('platform_name_ar'),
            'logo_path' => Setting::get('platform_logo_path'),
            'vat_number' => Setting::get('platform_vat_number', $billing['vat_number']),
            'cr_number' => Setting::get('platform_cr_number', $billing['cr_number']),
            'address' => Setting::get('platform_address', $billing['address']),
            'phone' => Setting::get('platform_phone'),
            'email' => Setting::get('platform_email', $billing['email']),
        ];
    }
}
