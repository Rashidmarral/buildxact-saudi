<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

/**
 * Overrides config('company.*') with values stored in the database (via the
 * admin Settings screen), so every existing Blade view that already calls
 * config('company.xxx') becomes admin-editable with zero template changes.
 * Falls back silently to config/company.php defaults if the settings table
 * doesn't exist yet (e.g. before the first migration) or has no override
 * for a given key.
 */
class SettingsServiceProvider extends ServiceProvider
{
    /**
     * Maps DB setting keys to their config('company.*') destination.
     */
    private const MAP = [
        'company_name' => 'company.name',
        'company_short_name' => 'company.short_name',
        'company_legal_name' => 'company.legal_name',
        'company_tagline' => 'company.tagline',
        'company_domain' => 'company.domain',
        'company_email' => 'company.email',
        'company_phone' => 'company.phone',
        'company_whatsapp' => 'company.whatsapp',
        'company_address' => 'company.address',
        'company_hours' => 'company.hours',
        'company_hours_short' => 'company.hours_short',
        'company_founded_year' => 'company.founded_year',
        'company_stats_years' => 'company.stats.years',
        'company_stats_professionals' => 'company.stats.professionals',
        'company_stats_monthly_reach' => 'company.stats.monthly_reach',
        'social_facebook' => 'company.social.facebook',
        'social_instagram' => 'company.social.instagram',
        'social_linkedin' => 'company.social.linkedin',
        'social_twitter' => 'company.social.twitter',
    ];

    public function boot(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $settings = Setting::allCached();
        } catch (\Throwable) {
            return;
        }

        foreach (self::MAP as $key => $configPath) {
            if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
                Config::set($configPath, $settings[$key]);
            }
        }

        if (! empty($settings['company_coverage_areas'])) {
            $areas = json_decode($settings['company_coverage_areas'], true);
            if (is_array($areas) && $areas !== []) {
                Config::set('company.coverage_areas', $areas);
            }
        }

        Config::set('company.home_hero_video_url', $settings['home_hero_video_url'] ?? null);
        Config::set('company.home_hero_video_path', $settings['home_hero_video_path'] ?? null);
    }
}
