<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\HandlesUploads;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SettingController extends Controller
{
    use HandlesUploads;

    /**
     * Settings this screen manages: key => [label, group, type].
     * Seeded with defaults by SettingSeeder; this list drives the form.
     */
    private const FIELDS = [
        'company_name' => ['Company Name', 'company', 'text'],
        'company_short_name' => ['Short Name (header logo)', 'company', 'text'],
        'company_legal_name' => ['Legal Name (footer copyright)', 'company', 'text'],
        'company_tagline' => ['Tagline', 'company', 'text'],
        'company_domain' => ['Website Domain', 'company', 'text'],
        'company_founded_year' => ['Founded Year', 'company', 'text'],
        'company_email' => ['Contact Email', 'contact', 'text'],
        'company_phone' => ['Phone Number', 'contact', 'text'],
        'company_whatsapp' => ['WhatsApp Number', 'contact', 'text'],
        'company_address' => ['Address', 'contact', 'textarea'],
        'company_hours' => ['Business Hours (full)', 'contact', 'text'],
        'company_hours_short' => ['Business Hours (short, footer)', 'contact', 'text'],
        'company_footer_about' => ['Footer About Text', 'company', 'textarea'],
        'company_stats_years' => ['Stat: Years of Excellence', 'stats', 'text'],
        'company_stats_professionals' => ['Stat: Professionals', 'stats', 'text'],
        'company_stats_monthly_reach' => ['Stat: Pharmacies Reached Monthly', 'stats', 'text'],
        'company_coverage_areas' => ['Coverage Areas (one per line)', 'stats', 'lines'],
        'social_facebook' => ['Facebook URL', 'social', 'text'],
        'social_instagram' => ['Instagram URL', 'social', 'text'],
        'social_linkedin' => ['LinkedIn URL', 'social', 'text'],
        'social_twitter' => ['Twitter / X URL', 'social', 'text'],
        'theme_primary_color' => ['Primary Brand Color', 'theme', 'color'],
        'theme_accent_color' => ['Accent Brand Color', 'theme', 'color'],
        'home_hero_video_url' => ['Home Hero Video URL (mp4 link, optional)', 'hero', 'text'],

        'about_hero_heading' => ['Hero Heading', 'about', 'text'],
        'about_hero_subheading' => ['Hero Subheading', 'about', 'textarea'],
        'about_story_tagline' => ['Story Section Tagline', 'about', 'text'],
        'about_story_heading' => ['Story Section Heading', 'about', 'text'],
        'about_story_body' => ['Story Text (blank line between paragraphs)', 'about', 'textarea'],

        'services_hero_heading' => ['Hero Heading', 'services', 'text'],
        'services_hero_subheading' => ['Hero Subheading', 'services', 'textarea'],
        'services_intro_tagline' => ['Intro Section Tagline', 'services', 'text'],
        'services_intro_heading' => ['Intro Section Heading', 'services', 'text'],
        'services_intro_body' => ['Intro Text', 'services', 'textarea'],

        'quality_hero_heading' => ['Hero Heading', 'quality', 'text'],
        'quality_hero_subheading' => ['Hero Subheading', 'quality', 'textarea'],
        'quality_intro_tagline' => ['Intro Section Tagline', 'quality', 'text'],
        'quality_intro_heading' => ['Intro Section Heading', 'quality', 'text'],
        'quality_intro_body' => ['Intro Text', 'quality', 'textarea'],

        'careers_hero_heading' => ['Hero Heading', 'careers', 'text'],
        'careers_hero_subheading' => ['Hero Subheading', 'careers', 'textarea'],
        'careers_cta_heading' => ['Bottom CTA Heading', 'careers', 'text'],
        'careers_cta_body' => ['Bottom CTA Text', 'careers', 'textarea'],
    ];

    public function edit()
    {
        $values = Setting::allCached();

        return view('admin.settings.edit', ['fields' => self::FIELDS, 'values' => $values]);
    }

    public function update(Request $request)
    {
        foreach (self::FIELDS as $key => [$label, $group, $type]) {
            $value = $request->input($key);

            if ($type === 'lines') {
                $lines = array_values(array_filter(array_map('trim', explode("\n", (string) $value))));
                $value = json_encode($lines);
            }

            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'label' => $label, 'type' => $type === 'lines' ? 'json' : $type]
            );
        }

        $videoPath = Setting::query()->where('key', 'home_hero_video_path')->value('value');
        $newVideoPath = $this->storeUpload($request, 'home_hero_video_file', 'hero-video', $videoPath);

        if ($newVideoPath !== $videoPath) {
            Setting::updateOrCreate(
                ['key' => 'home_hero_video_path'],
                ['value' => $newVideoPath, 'group' => 'hero', 'label' => 'Home Hero Video (uploaded file)', 'type' => 'video']
            );
        }

        if ($request->boolean('remove_home_hero_video') && $videoPath) {
            Storage::disk('public')->delete($videoPath);
            Setting::updateOrCreate(
                ['key' => 'home_hero_video_path'],
                ['value' => null, 'group' => 'hero', 'label' => 'Home Hero Video (uploaded file)', 'type' => 'video']
            );
        }

        $logoPath = Setting::query()->where('key', 'company_logo_path')->value('value');
        $newLogoPath = $this->storeUpload($request, 'company_logo_file', 'branding', $logoPath);

        if ($newLogoPath !== $logoPath) {
            Setting::updateOrCreate(
                ['key' => 'company_logo_path'],
                ['value' => $newLogoPath, 'group' => 'branding', 'label' => 'Site Logo', 'type' => 'image']
            );
        }

        if ($request->boolean('remove_company_logo') && $logoPath) {
            Storage::disk('public')->delete($logoPath);
            Setting::updateOrCreate(
                ['key' => 'company_logo_path'],
                ['value' => null, 'group' => 'branding', 'label' => 'Site Logo', 'type' => 'image']
            );
        }

        $ogImagePath = Setting::query()->where('key', 'seo_og_image_path')->value('value');
        $newOgImagePath = $this->storeUpload($request, 'seo_og_image_file', 'seo', $ogImagePath);

        if ($newOgImagePath !== $ogImagePath) {
            Setting::updateOrCreate(
                ['key' => 'seo_og_image_path'],
                ['value' => $newOgImagePath, 'group' => 'seo', 'label' => 'Social Share Image', 'type' => 'image']
            );
        }

        if ($request->boolean('remove_seo_og_image') && $ogImagePath) {
            Storage::disk('public')->delete($ogImagePath);
            Setting::updateOrCreate(
                ['key' => 'seo_og_image_path'],
                ['value' => null, 'group' => 'seo', 'label' => 'Social Share Image', 'type' => 'image']
            );
        }

        return redirect()->route('admin.settings.edit')->with('status', 'Settings updated.');
    }
}
