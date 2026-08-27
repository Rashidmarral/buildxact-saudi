<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = [
            'Analgesics & Pain Management' => [
                ['name' => 'Paracetamol Tablets', 'generic_name' => 'Paracetamol 500mg', 'pack_size' => 'Strip of 10', 'is_featured' => true],
                ['name' => 'Ibuprofen Tablets', 'generic_name' => 'Ibuprofen 400mg', 'pack_size' => 'Strip of 10'],
                ['name' => 'Diclofenac Gel', 'generic_name' => 'Diclofenac Sodium 1% w/w', 'pack_size' => '30g Tube'],
                ['name' => 'Tramadol Capsules', 'generic_name' => 'Tramadol HCl 50mg', 'pack_size' => 'Strip of 10'],
            ],
            'Antibiotics & Anti-Infectives' => [
                ['name' => 'Amoxicillin Capsules', 'generic_name' => 'Amoxicillin 500mg', 'pack_size' => 'Strip of 10', 'is_featured' => true],
                ['name' => 'Azithromycin Tablets', 'generic_name' => 'Azithromycin 500mg', 'pack_size' => 'Strip of 3'],
                ['name' => 'Ciprofloxacin Tablets', 'generic_name' => 'Ciprofloxacin 500mg', 'pack_size' => 'Strip of 10'],
                ['name' => 'Metronidazole Suspension', 'generic_name' => 'Metronidazole 200mg/5ml', 'pack_size' => '60ml Bottle'],
            ],
            'Cardiovascular Care' => [
                ['name' => 'Amlodipine Tablets', 'generic_name' => 'Amlodipine 5mg', 'pack_size' => 'Strip of 10'],
                ['name' => 'Atorvastatin Tablets', 'generic_name' => 'Atorvastatin 20mg', 'pack_size' => 'Strip of 10', 'is_featured' => true],
                ['name' => 'Losartan Tablets', 'generic_name' => 'Losartan Potassium 50mg', 'pack_size' => 'Strip of 10'],
            ],
            'Diabetic Care' => [
                ['name' => 'Metformin Tablets', 'generic_name' => 'Metformin HCl 500mg', 'pack_size' => 'Strip of 10', 'is_featured' => true],
                ['name' => 'Gliclazide Tablets', 'generic_name' => 'Gliclazide 80mg', 'pack_size' => 'Strip of 10'],
            ],
            'Gastroenterology' => [
                ['name' => 'Omeprazole Capsules', 'generic_name' => 'Omeprazole 20mg', 'pack_size' => 'Strip of 14', 'is_featured' => true],
                ['name' => 'Antacid Suspension', 'generic_name' => 'Aluminium Hydroxide + Magnesium Hydroxide', 'pack_size' => '200ml Bottle'],
                ['name' => 'ORS Sachets', 'generic_name' => 'Oral Rehydration Salts', 'pack_size' => 'Box of 25 Sachets'],
            ],
            'Respiratory Care' => [
                ['name' => 'Cetirizine Tablets', 'generic_name' => 'Cetirizine HCl 10mg', 'pack_size' => 'Strip of 10'],
                ['name' => 'Cough Syrup', 'generic_name' => 'Dextromethorphan + Guaifenesin', 'pack_size' => '120ml Bottle', 'is_featured' => true],
                ['name' => 'Salbutamol Inhaler', 'generic_name' => 'Salbutamol 100mcg/dose', 'pack_size' => '200 Dose Inhaler'],
            ],
            'Vitamins, Minerals & Supplements' => [
                ['name' => 'Multivitamin Capsules', 'generic_name' => 'Multivitamins & Minerals', 'pack_size' => 'Bottle of 30', 'is_featured' => true],
                ['name' => 'Vitamin C Tablets', 'generic_name' => 'Ascorbic Acid 500mg', 'pack_size' => 'Bottle of 30'],
                ['name' => 'Calcium + Vitamin D3 Tablets', 'generic_name' => 'Calcium Carbonate + Cholecalciferol', 'pack_size' => 'Strip of 10'],
                ['name' => 'Iron & Folic Acid Tablets', 'generic_name' => 'Ferrous Sulphate + Folic Acid', 'pack_size' => 'Strip of 10'],
            ],
            'Mother & Child Care' => [
                ['name' => 'Paediatric Paracetamol Syrup', 'generic_name' => 'Paracetamol 120mg/5ml', 'pack_size' => '60ml Bottle', 'is_featured' => true],
                ['name' => 'Gripe Water Drops', 'generic_name' => 'Dill Oil Formulation', 'pack_size' => '120ml Bottle'],
                ['name' => 'Folic Acid Tablets (Prenatal)', 'generic_name' => 'Folic Acid 5mg', 'pack_size' => 'Strip of 10'],
            ],
        ];

        foreach ($catalog as $categoryName => $products) {
            $category = ProductCategory::where('slug', Str::slug($categoryName))->first();

            if (! $category) {
                continue;
            }

            foreach ($products as $index => $product) {
                Product::updateOrCreate(
                    ['slug' => Str::slug($product['name'])],
                    [
                        'product_category_id' => $category->id,
                        'name' => $product['name'],
                        'generic_name' => $product['generic_name'],
                        'pack_size' => $product['pack_size'],
                        'manufacturer' => 'Sourced from DRAP-registered manufacturers',
                        'is_featured' => $product['is_featured'] ?? false,
                        'sort_order' => $index,
                    ]
                );
            }
        }
    }
}
