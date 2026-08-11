<?php

namespace Database\Seeders;

use App\Models\Principal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PrincipalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Note: these are fictional manufacturer names for demo purposes —
     * replace with real principal/manufacturer agreements before launch.
     */
    public function run(): void
    {
        $principals = [
            ['name' => 'Al-Noor Pharmaceuticals', 'initials' => 'AN', 'tagline' => 'Leading generic & branded manufacturer', 'years' => 8, 'products' => 60],
            ['name' => 'Meridian Health Sciences', 'initials' => 'MH', 'tagline' => 'Specialists in chronic-care therapies', 'years' => 6, 'products' => 45],
            ['name' => 'Crescent Pharma Industries', 'initials' => 'CP', 'tagline' => 'Quality healthcare products since 1995', 'years' => 9, 'products' => 70],
            ['name' => 'Vitalis Biotech', 'initials' => 'VB', 'tagline' => 'Injectables & cold-chain specialists', 'years' => 5, 'products' => 35],
            ['name' => 'Northline Laboratories', 'initials' => 'NL', 'tagline' => 'Trusted OTC & consumer healthcare', 'years' => 7, 'products' => 50],
            ['name' => 'Horizon Pharma', 'initials' => 'HP', 'tagline' => 'Innovative respiratory & cardiac care', 'years' => 4, 'products' => 30],
            ['name' => 'Pure Line Laboratories', 'initials' => 'PL', 'tagline' => 'Excellence in nutraceuticals', 'years' => 6, 'products' => 40],
            ['name' => 'Zenith Health Care', 'initials' => 'ZH', 'tagline' => 'Comprehensive mother & child care range', 'years' => 5, 'products' => 38],
        ];

        foreach ($principals as $index => $principal) {
            Principal::updateOrCreate(
                ['slug' => Str::slug($principal['name'])],
                [
                    'name' => $principal['name'],
                    'initials' => $principal['initials'],
                    'tagline' => $principal['tagline'],
                    'description' => "Authorized distribution partner for {$principal['name']}'s full product range across Muzaffargarh and South Punjab.",
                    'years_partnership' => $principal['years'],
                    'products_count' => $principal['products'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
