<?php

namespace Database\Seeders;

use App\Models\NavItem;
use App\Models\Page;
use Illuminate\Database\Seeder;

class NavItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            ['location' => 'header', 'label' => 'Home', 'route_name' => 'home', 'sort_order' => 1],
            ['location' => 'header', 'label' => 'About', 'route_name' => 'about', 'sort_order' => 2],
            ['location' => 'header', 'label' => 'Principals', 'route_name' => 'principals', 'sort_order' => 3],
            ['location' => 'header', 'label' => 'Catalog', 'route_name' => 'catalog.index', 'sort_order' => 4],
            ['location' => 'header', 'label' => 'Services', 'route_name' => 'services', 'sort_order' => 5],

            ['location' => 'header_more', 'label' => 'Quality & Certifications', 'route_name' => 'quality', 'sort_order' => 1],
            ['location' => 'header_more', 'label' => 'Gallery', 'route_name' => 'gallery', 'sort_order' => 2],
            ['location' => 'header_more', 'label' => 'Careers', 'route_name' => 'careers', 'sort_order' => 3],
            ['location' => 'header_more', 'label' => 'FAQs', 'route_name' => 'faq', 'sort_order' => 4],

            ['location' => 'footer_company', 'label' => 'About', 'route_name' => 'about', 'sort_order' => 1],
            ['location' => 'footer_company', 'label' => 'Principals', 'route_name' => 'principals', 'sort_order' => 2],
            ['location' => 'footer_company', 'label' => 'Services', 'route_name' => 'services', 'sort_order' => 3],
            ['location' => 'footer_company', 'label' => 'Quality & Certifications', 'route_name' => 'quality', 'sort_order' => 4],
            ['location' => 'footer_company', 'label' => 'Careers', 'route_name' => 'careers', 'sort_order' => 5],

            ['location' => 'footer_resources', 'label' => 'Catalog', 'route_name' => 'catalog.index', 'sort_order' => 1],
            ['location' => 'footer_resources', 'label' => 'Gallery', 'route_name' => 'gallery', 'sort_order' => 2],
            ['location' => 'footer_resources', 'label' => 'FAQs', 'route_name' => 'faq', 'sort_order' => 3],
            ['location' => 'footer_resources', 'label' => 'Contact', 'route_name' => 'contact', 'sort_order' => 4],
        ];

        foreach ($items as $item) {
            NavItem::updateOrCreate(
                ['location' => $item['location'], 'route_name' => $item['route_name']],
                [
                    'label' => $item['label'],
                    'sort_order' => $item['sort_order'],
                    'is_visible' => true,
                    'open_in_new_tab' => false,
                ]
            );
        }

        $legalPages = [
            'privacy-policy' => ['label' => 'Privacy Policy', 'sort_order' => 1],
            'terms-of-service' => ['label' => 'Terms of Service', 'sort_order' => 2],
        ];

        foreach ($legalPages as $slug => $meta) {
            $page = Page::where('slug', $slug)->first();

            if (! $page) {
                continue;
            }

            NavItem::updateOrCreate(
                ['location' => 'footer_legal', 'page_id' => $page->id],
                [
                    'label' => $meta['label'],
                    'sort_order' => $meta['sort_order'],
                    'is_visible' => true,
                    'open_in_new_tab' => false,
                ]
            );
        }
    }
}
