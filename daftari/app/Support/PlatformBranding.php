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
            'name' => Setting::get('general_platform_name', Setting::get('platform_name', $billing['company_name'])),
            'name_ar' => Setting::get('platform_name_ar'),
            'logo_path' => Setting::get('platform_logo_path'),
            'vat_number' => Setting::get('platform_vat_number', $billing['vat_number']),
            'cr_number' => Setting::get('platform_cr_number', $billing['cr_number']),
            'address' => Setting::get('platform_address', $billing['address']),
            'phone' => Setting::get('platform_phone'),
            'email' => Setting::get('platform_email', $billing['email']),
            'website' => Setting::get('platform_website'),
            'favicon_path' => Setting::get('platform_favicon_path'),
            'login_logo_path' => Setting::get('branding_login_logo_path'),
            'pdf_logo_path' => Setting::get('branding_pdf_logo_path'),
            'email_logo_path' => Setting::get('branding_email_logo_path'),
            'primary_color' => Setting::get('branding_primary_color'),
            'secondary_color' => Setting::get('branding_secondary_color'),
            'sidebar_color' => Setting::get('branding_sidebar_color'),
        ];
    }
}
