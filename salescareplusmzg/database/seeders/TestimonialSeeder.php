<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $testimonials = [
            [
                'name' => 'Dr. Imran Farooq',
                'role' => 'Owner',
                'organization' => 'Farooq Medical Store, Muzaffargarh',
                'quote' => 'Sales Care Plus MZG has been our most reliable medicine supplier for over three years. Orders arrive on time, every time, and their team genuinely cares about getting it right.',
                'rating' => 5,
            ],
            [
                'name' => 'Rashid Mehmood',
                'role' => 'Procurement Manager',
                'organization' => 'City Care Pharmacy',
                'quote' => 'What stands out is their honesty about stock and pricing. No surprises, no shortages we were not warned about — just consistent, professional distribution.',
                'rating' => 5,
            ],
            [
                'name' => 'Ayesha Noor',
                'role' => 'Pharmacist',
                'organization' => 'Noor Pharma, Alipur Road',
                'quote' => 'Their cold-chain handling for temperature-sensitive medicines gives us real confidence. It shows they invest in quality, not just quantity.',
                'rating' => 5,
            ],
        ];

        foreach ($testimonials as $index => $testimonial) {
            Testimonial::updateOrCreate(
                ['name' => $testimonial['name'], 'organization' => $testimonial['organization']],
                [
                    'role' => $testimonial['role'],
                    'quote' => $testimonial['quote'],
                    'rating' => $testimonial['rating'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
