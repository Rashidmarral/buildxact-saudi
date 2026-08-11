<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageSection;
use Illuminate\Database\Seeder;

class LegalPageSeeder extends Seeder
{
    /**
     * Seeds generic starter Privacy Policy / Terms of Service pages so the
     * site isn't missing them entirely. This is placeholder boilerplate,
     * not legal advice — have a lawyer review before relying on it.
     */
    public function run(): void
    {
        $this->seedPage(
            'Privacy Policy',
            'privacy-policy',
            'How we collect, use and protect the information you share with us.',
            <<<'TEXT'
            This Privacy Policy explains how we collect, use, and protect information when you visit our website or contact us for business.

            Information We Collect

            When you fill out our contact form, subscribe to our newsletter, or apply for a job, we collect the details you provide — such as your name, email address, phone number, and any message or resume you submit. We do not collect payment or financial information through this website.

            How We Use Your Information

            We use the information you provide to respond to your enquiries, process job applications, send newsletter updates you've subscribed to, and improve our services. We do not sell or rent your personal information to third parties.

            Data Storage & Security

            Information submitted through our website is stored securely in our database and accessible only to authorized staff. We take reasonable measures to protect your data from unauthorized access, loss, or misuse.

            Cookies

            Our website may use basic cookies to remember your preferences and improve your browsing experience. You can disable cookies in your browser settings at any time.

            Your Rights

            You may request access to, correction of, or deletion of the personal information we hold about you by contacting us using the details on our Contact page.

            Changes to This Policy

            We may update this Privacy Policy from time to time. Any changes will be posted on this page.
            TEXT
        );

        $this->seedPage(
            'Terms of Service',
            'terms-of-service',
            'The terms that govern your use of our website and services.',
            <<<'TEXT'
            By accessing and using this website, you agree to the following terms.

            Use of This Website

            This website is provided for informational purposes to help pharmacies, hospitals, clinics, and other healthcare providers learn about our products and services and get in touch with us. You agree not to misuse this website or attempt to disrupt its normal operation.

            Product Information

            Product listings on this website are provided for reference. Pricing, availability and specifications are subject to change and confirmed directly with our sales team at the time of order. Nothing on this website constitutes medical advice — always consult a qualified healthcare professional.

            Orders & Business Relationships

            This website does not process online orders or payments. All orders, quotes and business agreements are handled directly through our sales team via phone, WhatsApp, email or in person, and are subject to separately agreed terms.

            Intellectual Property

            All content on this website — including text, images, and branding — belongs to us or is used with permission, and may not be reproduced without our consent.

            Limitation of Liability

            We make reasonable efforts to keep information on this website accurate and up to date, but we do not guarantee completeness or accuracy, and are not liable for any loss arising from reliance on this website's content.

            Changes to These Terms

            We may update these Terms of Service from time to time. Continued use of this website after changes are posted constitutes acceptance of the updated terms.
            TEXT
        );
    }

    private function seedPage(string $title, string $slug, string $metaDescription, string $body): void
    {
        $page = Page::updateOrCreate(
            ['slug' => $slug],
            ['title' => $title, 'meta_description' => $metaDescription, 'is_published' => true, 'sort_order' => 99]
        );

        PageSection::updateOrCreate(
            ['page_id' => $page->id, 'type' => 'rich_text'],
            [
                'heading' => $title,
                'body' => $body,
                'background' => 'white',
                'animation' => 'fade',
                'sort_order' => 1,
                'is_visible' => true,
            ]
        );
    }
}
