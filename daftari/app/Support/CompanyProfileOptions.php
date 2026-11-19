<?php

namespace App\Support;

/**
 * Fixed option lists collected on the signup wizard (registration only, so
 * far — see resources/views/auth/register.blade.php). Kept as one small
 * catalog class rather than master-data tables since nothing else in the
 * app currently depends on these values beyond storing what the company
 * picked at signup.
 */
class CompanyProfileOptions
{
    public static function organizationSizes(): array
    {
        return [
            '1-10' => __('1–10 employees'),
            '11-50' => __('11–50 employees'),
            '51-200' => __('51–200 employees'),
            '201-500' => __('201–500 employees'),
            '500+' => __('500+ employees'),
        ];
    }

    public static function industries(): array
    {
        return [
            'retail_trading' => __('Retail & Trading'),
            'construction' => __('Construction & Contracting'),
            'professional_services' => __('Professional Services'),
            'manufacturing' => __('Manufacturing'),
            'food_hospitality' => __('Food & Hospitality'),
            'healthcare' => __('Healthcare'),
            'real_estate' => __('Real Estate'),
            'technology' => __('Technology'),
            'logistics_transportation' => __('Logistics & Transportation'),
            'other' => __('Other'),
        ];
    }

    public static function jobTitles(): array
    {
        return [
            'owner_ceo' => __('Owner / CEO'),
            'manager' => __('Manager'),
            'finance_accounting' => __('Finance / Accounting'),
            'sales' => __('Sales'),
            'operations' => __('Operations'),
            'other' => __('Other'),
        ];
    }

    /**
     * Same values/labels as Settings' own "Primary customer type" field
     * (User\SettingsController) — kept identical so a company's answer at
     * signup reads the same way later in Settings.
     */
    public static function customerTypes(): array
    {
        return [
            'mixed' => __('Mixed (both)'),
            'b2b' => __('Businesses (B2B)'),
            'b2c' => __('Individuals (B2C)'),
        ];
    }
}
