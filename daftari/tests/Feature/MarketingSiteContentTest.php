<?php

namespace Tests\Feature;

use App\Models\CmsSection;
use App\Models\Plan;
use Database\Seeders\CmsContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requested: the marketing site should list every module the platform now
 * ships, including the new Payroll and POS modules, and give About/Contact
 * more real content. Covers that CmsContentSeeder (what a fresh install
 * runs) actually puts Payroll/POS on the home and features grids, that the
 * pricing comparison table surfaces the has_payroll/has_pos plan flags, and
 * that About/Contact render their new sections.
 */
class MarketingSiteContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_and_features_pages_list_payroll_and_pos(): void
    {
        $this->seed(CmsContentSeeder::class);

        $this->get(route('home'))->assertOk()->assertSee('Full payroll, GOSI & WPS')->assertSee('Point of sale (POS)');
        $this->get(route('features'))->assertOk()->assertSee('Full payroll, GOSI & WPS')->assertSee('Point of sale (POS)');
    }

    public function test_about_page_shows_the_new_stats_and_testimonials_sections(): void
    {
        $this->seed(CmsContentSeeder::class);

        $response = $this->get(route('about'));

        $response->assertOk();
        $response->assertSee('One platform, every module');
        $response->assertSee('Connected modules');
        $response->assertSee('What business owners say');
        $response->assertSee('Finance Manager');
    }

    public function test_contact_page_shows_a_sales_channel_and_the_new_faq_entries(): void
    {
        $this->seed(CmsContentSeeder::class);

        $response = $this->get(route('contact'));

        $response->assertOk();
        $response->assertSee('sales@daftari.app');
        $response->assertSee('Does Daftari include payroll and a point of sale?');
    }

    /**
     * Regression: an earlier data migration (expand_cms_content_and_add_
     * global_blocks) pre-creates one row each for the about/contact pages
     * before this seeder ever runs on a truly fresh install. The seeder's
     * old "skip this page if it already has any content" check saw that
     * one row and skipped seeding the rest of the page entirely — so a
     * fresh install's About page had no hero and no pillars, and its
     * Contact page had no hero and no contact methods at all, just a
     * lone FAQ block. Covers both pages end up complete and in the right
     * order regardless of that pre-existing partial content.
     */
    public function test_about_and_contact_are_fully_seeded_on_a_fresh_install_despite_migration_preseeded_rows(): void
    {
        $this->seed(CmsContentSeeder::class);

        $aboutTypes = CmsSection::where('page', 'about')->orderBy('sort_order')->pluck('type')->toArray();
        $this->assertSame(['text', 'feature_grid', 'text', 'stats', 'testimonials'], $aboutTypes);
        $this->assertSame('About Daftari', CmsSection::where('page', 'about')->orderBy('sort_order')->first()->title_en);

        $contactTypes = CmsSection::where('page', 'contact')->orderBy('sort_order')->pluck('type')->toArray();
        $this->assertSame(['hero', 'contact_info', 'social_links', 'faq'], $contactTypes);

        $aboutResponse = $this->get(route('about'));
        $aboutResponse->assertOk()->assertSeeInOrder(['About Daftari', 'Compliance first', 'How we build Daftari', 'One platform, every module', 'What business owners say']);

        $contactResponse = $this->get(route('contact'));
        $contactResponse->assertOk()->assertSeeInOrder(['Contact us', 'Email', 'sales@daftari.app', 'Before you reach out']);
    }

    public function test_pricing_comparison_table_reflects_the_payroll_and_pos_plan_flags(): void
    {
        Plan::create([
            'name' => 'Business', 'slug' => 'business-'.uniqid(), 'price_monthly' => 109, 'price_yearly' => 1090,
            'is_active' => true, 'is_public' => true, 'has_payroll' => true, 'has_pos' => true,
        ]);

        $response = $this->get(route('pricing'));

        $response->assertOk();
        $response->assertSee('Payroll &amp; Point of Sale', false);
        $response->assertSee('Full WPS-compliant payroll (GOSI, end-of-service, payslips)');
        $response->assertSee('Retail point of sale (registers, shifts, split payments, ZATCA receipts)');
    }
}
