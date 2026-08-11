<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faqs = [
            [
                'category' => 'Ordering',
                'question' => 'How do I set up a new pharmacy account with Sales Care Plus MZG?',
                'answer' => 'Contact our team with your pharmacy or hospital details and drug licence. Once verified, our sales representative will open your account and share our current catalogue and pricing — usually within one business day.',
            ],
            [
                'category' => 'Ordering',
                'question' => 'What is the minimum order value?',
                'answer' => 'There is no strict minimum for existing accounts, though we recommend consolidating orders where possible to make the most of same-day dispatch. New accounts may have an introductory minimum — ask your sales representative for current terms.',
            ],
            [
                'category' => 'Ordering',
                'question' => 'How can I place an order?',
                'answer' => 'Orders can be placed by phone, WhatsApp, or in person with your assigned sales representative. We are working on an online ordering portal for partner pharmacies.',
            ],
            [
                'category' => 'Delivery',
                'question' => 'How fast is delivery after I place an order?',
                'answer' => 'Most orders placed before our daily cut-off are dispatched same-day and delivered within 24 hours across Muzaffargarh and nearby tehsils. Areas further from our warehouse may take up to 48 hours.',
            ],
            [
                'category' => 'Delivery',
                'question' => 'Do you deliver temperature-sensitive medicines safely?',
                'answer' => 'Yes. Cold-chain products are stored in monitored, temperature-controlled units and transported using insulated packaging to preserve potency from our warehouse to your shelf.',
            ],
            [
                'category' => 'Products',
                'question' => 'Can I request a product that is not in your catalogue?',
                'answer' => 'Yes — share the product details with your sales representative. If it falls within one of our principal manufacturers\' ranges, we can usually source and add it to your next order.',
            ],
            [
                'category' => 'Products',
                'question' => 'Are all your products DRAP-registered?',
                'answer' => 'Yes. Every product we distribute is sourced from DRAP-registered manufacturers and importers, and stored under GDP-compliant conditions in our Muzaffargarh warehouse.',
            ],
            [
                'category' => 'Partnerships',
                'question' => 'How can a pharmaceutical manufacturer become a principal partner?',
                'answer' => 'We are always open to representing new manufacturers across South Punjab. Reach out through our Contact page with your company details and product range, and our team will follow up to discuss terms.',
            ],
            [
                'category' => 'Account',
                'question' => 'What payment terms do you offer?',
                'answer' => 'Payment terms are agreed individually with each partner pharmacy based on order volume and account history. Ask your sales representative for the terms available to you.',
            ],
            [
                'category' => 'Account',
                'question' => 'Who do I contact if there is an issue with my order?',
                'answer' => 'Contact our customer support team by phone or WhatsApp with your order details, and we will resolve it promptly — replacements, credit notes or corrections are handled the same day wherever possible.',
            ],
        ];

        foreach ($faqs as $index => $faq) {
            Faq::updateOrCreate(
                ['question' => $faq['question']],
                [
                    'answer' => $faq['answer'],
                    'category' => $faq['category'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
