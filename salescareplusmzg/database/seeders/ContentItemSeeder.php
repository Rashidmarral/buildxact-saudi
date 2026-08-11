<?php

namespace Database\Seeders;

use App\Models\ContentItem;
use Illuminate\Database\Seeder;

class ContentItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Services page — service cards
            ['group' => 'service', 'icon' => 'warehouse', 'title' => 'Bulk Warehousing', 'description' => 'Climate-controlled storage across our Muzaffargarh facility, organised by category and batch for fast, accurate order picking.'],
            ['group' => 'service', 'icon' => 'thermometer', 'title' => 'Cold-Chain Handling', 'description' => 'Temperature-sensitive medicines are stored and transported under monitored conditions, preserving potency from warehouse to shelf.'],
            ['group' => 'service', 'icon' => 'truck', 'title' => 'Last-Mile Delivery', 'description' => 'A dedicated local fleet covering Muzaffargarh, Alipur, Kot Addu and Jatoi with scheduled, reliable delivery routes.'],
            ['group' => 'service', 'icon' => 'file-check', 'title' => 'Order & Inventory Management', 'description' => 'Simple ordering, transparent invoicing, and proactive stock alerts so partner pharmacies never run short.'],
            ['group' => 'service', 'icon' => 'shield', 'title' => 'Regulatory Compliance', 'description' => 'Every product we distribute is sourced from DRAP-registered manufacturers and handled under GDP guidelines.'],
            ['group' => 'service', 'icon' => 'headset', 'title' => 'Dedicated Account Support', 'description' => 'A named representative for every partner pharmacy — for order queries, product information and account support.'],

            // Services page — "How It Works" steps
            ['group' => 'service_process_step', 'icon' => '01', 'title' => 'Set Up Your Account', 'description' => 'Share your pharmacy or hospital details and licence — our team verifies and opens your account.'],
            ['group' => 'service_process_step', 'icon' => '02', 'title' => 'Browse & Order', 'description' => 'Get our current catalogue and pricing, then place orders by phone, WhatsApp, or in person.'],
            ['group' => 'service_process_step', 'icon' => '03', 'title' => 'We Pick & Pack', 'description' => 'Your order is picked from our GDP-compliant warehouse and quality-checked before dispatch.'],
            ['group' => 'service_process_step', 'icon' => '04', 'title' => 'On-Time Delivery', 'description' => 'Our fleet delivers to your door on schedule, with clear invoicing every time.'],

            // Careers page — job openings
            ['group' => 'job_opening', 'title' => 'Medical Sales Representative', 'subtitle' => 'Full-time · Muzaffargarh', 'description' => 'Build relationships with pharmacies and clinics, manage orders and grow our partner network across your territory.'],
            ['group' => 'job_opening', 'title' => 'Warehouse & Inventory Officer', 'subtitle' => 'Full-time · Muzaffargarh', 'description' => 'Manage stock receiving, storage conditions, batch tracking and order picking in our distribution warehouse.'],
            ['group' => 'job_opening', 'title' => 'Delivery Rider / Driver', 'subtitle' => 'Full-time · Muzaffargarh & nearby tehsils', 'description' => 'Deliver medicines safely and on schedule to pharmacies and hospitals across our coverage area.'],
            ['group' => 'job_opening', 'title' => 'Customer Support Officer', 'subtitle' => 'Full-time · Muzaffargarh', 'description' => 'Handle pharmacy order queries, account support and coordination between sales and warehouse teams.'],

            // Quality page — "How We Protect Product Quality"
            ['group' => 'quality_standard', 'icon' => 'thermometer', 'title' => 'Temperature Monitoring', 'description' => 'Continuous monitoring of storage areas to keep sensitive medicines within safe ranges.'],
            ['group' => 'quality_standard', 'icon' => 'file-check', 'title' => 'Batch Traceability', 'description' => 'Every product is tracked by batch and expiry date from receipt to dispatch.'],
            ['group' => 'quality_standard', 'icon' => 'shield', 'title' => 'Verified Sourcing', 'description' => 'We only stock products from licensed, DRAP-registered manufacturers and importers.'],
            ['group' => 'quality_standard', 'icon' => 'clock', 'title' => 'Stock Rotation', 'description' => 'First-expiry-first-out handling ensures pharmacies always receive fresh stock.'],

            // About page — story checklist
            ['group' => 'about_highlight', 'title' => 'Quality Assurance', 'description' => 'We maintain strict quality control standards for all distributed products.'],
            ['group' => 'about_highlight', 'title' => 'Cold Chain Management', 'description' => 'Temperature-controlled storage and transportation for sensitive products.'],
            ['group' => 'about_highlight', 'title' => 'Traceability', 'description' => 'Complete batch traceability from manufacturer to end customer.'],

            // About page — mission / vision / values
            ['group' => 'about_value', 'icon' => 'badge-check', 'title' => 'Our Mission', 'description' => 'To ensure the consistent availability of quality healthcare products across South Punjab through ethical and reliable distribution practices.'],
            ['group' => 'about_value', 'icon' => 'globe', 'title' => 'Our Vision', 'description' => 'To be the most trusted pharmaceutical distribution partner in the region, setting benchmarks for reliability, professionalism and excellence.'],
            ['group' => 'about_value', 'icon' => 'heart', 'title' => 'Our Values', 'description' => 'Integrity, quality, customer-centricity, and continuous improvement in everything we do.'],
        ];

        $order = [];

        foreach ($items as $item) {
            $order[$item['group']] = ($order[$item['group']] ?? 0) + 1;

            ContentItem::updateOrCreate(
                ['group' => $item['group'], 'title' => $item['title']],
                [
                    'icon' => $item['icon'] ?? null,
                    'subtitle' => $item['subtitle'] ?? null,
                    'description' => $item['description'] ?? null,
                    'sort_order' => $order[$item['group']],
                    'is_visible' => true,
                ]
            );
        }
    }
}
