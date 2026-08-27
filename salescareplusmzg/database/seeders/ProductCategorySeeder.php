<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Analgesics & Pain Management',
                'icon' => 'pill',
                'description' => 'Fast-acting relief for fever, headache, muscular and post-operative pain.',
            ],
            [
                'name' => 'Antibiotics & Anti-Infectives',
                'icon' => 'shield',
                'description' => 'Broad and narrow-spectrum antibacterials for primary and hospital care.',
            ],
            [
                'name' => 'Cardiovascular Care',
                'icon' => 'heart',
                'description' => 'Medicines for hypertension, cholesterol management and heart health.',
            ],
            [
                'name' => 'Diabetic Care',
                'icon' => 'droplet',
                'description' => 'Oral anti-diabetics and supportive therapies for blood sugar management.',
            ],
            [
                'name' => 'Gastroenterology',
                'icon' => 'leaf',
                'description' => 'Antacids, PPIs and digestive-care formulations for everyday gut health.',
            ],
            [
                'name' => 'Respiratory Care',
                'icon' => 'wind',
                'description' => 'Cough, cold, allergy and asthma-care medicines for every season.',
            ],
            [
                'name' => 'Vitamins, Minerals & Supplements',
                'icon' => 'sun',
                'description' => 'Nature-derived multivitamins and supplements to support daily wellness.',
            ],
            [
                'name' => "Mother & Child Care",
                'icon' => 'sprout',
                'description' => 'Pediatric syrups, drops and maternal-health formulations, gently dosed.',
            ],
        ];

        foreach ($categories as $index => $category) {
            ProductCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'icon' => $category['icon'],
                    'description' => $category['description'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
