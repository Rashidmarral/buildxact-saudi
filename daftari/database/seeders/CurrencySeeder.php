<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // Real-world decimal precision: KWD/BHD/OMR/JOD are 3-decimal
        // currencies (their smallest unit is 1/1000, not 1/100) — everything
        // else here uses the usual 2. Symbol position 'before' matches this
        // app's existing "SAR 100.00" convention everywhere it's shown.
        $currencies = [
            ['code' => 'SAR', 'name' => 'Saudi Riyal', 'symbol' => 'SAR', 'decimal_places' => 2, 'is_default' => true, 'sort_order' => 1],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'sort_order' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2, 'sort_order' => 3],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'decimal_places' => 2, 'sort_order' => 4],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'decimal_places' => 2, 'sort_order' => 5],
            ['code' => 'KWD', 'name' => 'Kuwaiti Dinar', 'symbol' => 'KWD', 'decimal_places' => 3, 'sort_order' => 6],
            ['code' => 'BHD', 'name' => 'Bahraini Dinar', 'symbol' => 'BHD', 'decimal_places' => 3, 'sort_order' => 7],
            ['code' => 'QAR', 'name' => 'Qatari Riyal', 'symbol' => 'QAR', 'decimal_places' => 2, 'sort_order' => 8],
            ['code' => 'OMR', 'name' => 'Omani Rial', 'symbol' => 'OMR', 'decimal_places' => 3, 'sort_order' => 9],
            ['code' => 'EGP', 'name' => 'Egyptian Pound', 'symbol' => 'EGP', 'decimal_places' => 2, 'sort_order' => 10],
            ['code' => 'JOD', 'name' => 'Jordanian Dinar', 'symbol' => 'JOD', 'decimal_places' => 3, 'sort_order' => 11],
            ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'decimal_places' => 2, 'sort_order' => 12],
        ];

        foreach ($currencies as $currency) {
            Currency::query()->updateOrCreate(
                ['code' => $currency['code']],
                $currency + [
                    'decimal_separator' => '.',
                    'thousands_separator' => ',',
                    'symbol_position' => 'before',
                    'is_active' => true,
                    'is_default' => $currency['is_default'] ?? false,
                ]
            );
        }
    }
}
