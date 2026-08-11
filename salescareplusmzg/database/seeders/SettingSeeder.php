<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company_name' => ['Sales Care Plus MZG', 'company', 'text', 'Company Name'],
            'company_short_name' => ['SalesCare+ MZG', 'company', 'text', 'Short Name (header logo)'],
            'company_legal_name' => ['Sales Care Plus MZG (Pvt) Ltd.', 'company', 'text', 'Legal Name (footer copyright)'],
            'company_tagline' => ['Pharmaceutical Distribution · Muzaffargarh', 'company', 'text', 'Tagline'],
            'company_domain' => ['salescareplusmzg.com', 'company', 'text', 'Website Domain'],
            'company_founded_year' => ['2014', 'company', 'text', 'Founded Year'],
            'company_email' => [config('company.email'), 'contact', 'text', 'Contact Email'],
            'company_phone' => [config('company.phone'), 'contact', 'text', 'Phone Number'],
            'company_whatsapp' => [config('company.whatsapp'), 'contact', 'text', 'WhatsApp Number'],
            'company_address' => [config('company.address'), 'contact', 'textarea', 'Address'],
            'company_hours' => ['Saturday – Thursday, 9:00 AM – 8:00 PM', 'contact', 'text', 'Business Hours (full)'],
            'company_hours_short' => ['Sat–Thu: 9:00 AM – 8:00 PM', 'contact', 'text', 'Business Hours (short, footer)'],
            'company_footer_about' => [
                'Leading pharmaceutical distribution organisation serving healthcare providers across South Punjab, headquartered in Muzaffargarh since 2014.',
                'company', 'textarea', 'Footer About Text',
            ],
            'theme_primary_color' => ['#2a9078', 'theme', 'color', 'Primary Brand Color'],
            'theme_accent_color' => ['#e35f38', 'theme', 'color', 'Accent Brand Color'],
            'company_stats_years' => ['10', 'stats', 'text', 'Stat: Years of Excellence'],
            'company_stats_professionals' => ['45', 'stats', 'text', 'Stat: Professionals'],
            'company_stats_monthly_reach' => ['250+', 'stats', 'text', 'Stat: Pharmacies Reached Monthly'],
            'company_coverage_areas' => [
                json_encode(['Muzaffargarh', 'Alipur', 'Kot Addu', 'Jatoi', 'Multan', 'Rangpur', 'Ali Pur Chattha', 'Sanawan']),
                'stats', 'json', 'Coverage Areas (one per line)',
            ],
            'social_facebook' => ['https://facebook.com/salescareplusmzg', 'social', 'text', 'Facebook URL'],
            'social_instagram' => ['https://instagram.com/salescareplusmzg', 'social', 'text', 'Instagram URL'],
            'social_linkedin' => ['https://linkedin.com/company/salescareplusmzg', 'social', 'text', 'LinkedIn URL'],
            'social_twitter' => ['https://twitter.com/salescareplusmzg', 'social', 'text', 'Twitter / X URL'],
            'home_hero_video_url' => [null, 'hero', 'text', 'Home Hero Video URL (mp4 link, optional)'],
        ];

        foreach ($defaults as $key => [$value, $group, $type, $label]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'type' => $type, 'label' => $label]
            );
        }
    }
}
