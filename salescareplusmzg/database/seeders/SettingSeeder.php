<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'maintenance_mode_enabled' => ['0', 'maintenance', 'checkbox', 'Maintenance Mode'],
            'maintenance_message' => [
                "We're making some improvements to our website right now. We'll be back online shortly — thanks for your patience.",
                'maintenance', 'textarea', 'Maintenance Message',
            ],

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

            'about_hero_heading' => ['A Decade of Pharmaceutical Excellence', 'about', 'text', 'Hero Heading'],
            'about_hero_subheading' => ['Headquartered in Muzaffargarh, serving healthcare providers across South Punjab.', 'about', 'textarea', 'Hero Subheading'],
            'about_story_tagline' => ['Our Story', 'about', 'text', 'Story Section Tagline'],
            'about_story_heading' => ['Building Trust Through Quality Distribution', 'about', 'text', 'Story Section Heading'],
            'about_story_body' => [
                "Headquartered in Muzaffargarh, Sales Care Plus MZG has built a distinguished reputation for reliability, professionalism and excellence since 2014. What began as a single-warehouse operation has grown into a full-scale distribution network covering Muzaffargarh, Alipur, Kot Addu, Jatoi and the surrounding tehsils.\n\nWith a dedicated team of professionals and a growing network of principal manufacturers, we ensure the consistent availability of quality healthcare products across our coverage area — every order, every time.",
                'about', 'textarea', 'Story Text (blank line between paragraphs)',
            ],

            'services_hero_heading' => ['End-to-End Distribution Services', 'services', 'text', 'Hero Heading'],
            'services_hero_subheading' => ["We handle every step between the manufacturer's gate and the pharmacy shelf, so our partners can focus on caring for patients.", 'services', 'textarea', 'Hero Subheading'],
            'services_intro_tagline' => ['Reliable, Every Route', 'services', 'text', 'Intro Section Tagline'],
            'services_intro_heading' => ['Built for the Realities of Local Distribution', 'services', 'text', 'Intro Section Heading'],
            'services_intro_body' => [
                "Muzaffargarh's pharmacies can't afford stock-outs. That's why our services are designed around dependable fundamentals — climate-controlled storage, verified sourcing, and a delivery fleet that runs on schedule, rain or shine.",
                'services', 'textarea', 'Intro Text',
            ],

            'quality_hero_heading' => ['Quality You Can Verify', 'quality', 'text', 'Hero Heading'],
            'quality_hero_subheading' => ['Every medicine that leaves our warehouse is handled under strict quality and regulatory standards — because the people receiving it deserve nothing less.', 'quality', 'textarea', 'Hero Subheading'],
            'quality_intro_tagline' => ['Cold-Chain Discipline', 'quality', 'text', 'Intro Section Tagline'],
            'quality_intro_heading' => ['Every Degree Monitored, Every Batch Tracked', 'quality', 'text', 'Intro Section Heading'],
            'quality_intro_body' => [
                'Temperature-sensitive medicines lose potency fast when handling slips. Our storage units are monitored continuously, and every batch is logged from receipt to dispatch — so what leaves our warehouse is exactly as effective as when it arrived.',
                'quality', 'textarea', 'Intro Text',
            ],

            'careers_hero_heading' => ['Build Your Career With Us', 'careers', 'text', 'Hero Heading'],
            'careers_hero_subheading' => ["We're always looking for reliable, caring people to join our warehouse, sales and logistics teams in Muzaffargarh.", 'careers', 'textarea', 'Hero Subheading'],
            'careers_cta_heading' => ["Don't see a role that fits?", 'careers', 'text', 'Bottom CTA Heading'],
            'careers_cta_body' => ["Send us your CV through the contact form — we're always happy to hear from motivated people.", 'careers', 'textarea', 'Bottom CTA Text'],
        ];

        foreach ($defaults as $key => [$value, $group, $type, $label]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group' => $group, 'type' => $type, 'label' => $label]
            );
        }
    }
}
