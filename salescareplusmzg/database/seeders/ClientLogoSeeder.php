<?php

namespace Database\Seeders;

use App\Models\ClientLogo;
use Illuminate\Database\Seeder;

class ClientLogoSeeder extends Seeder
{
    /**
     * Fictional client names for demo purposes — replace with real client
     * logos before launch, same approach as PrincipalSeeder.
     */
    public function run(): void
    {
        $clients = [
            'Al-Shifa Pharmacy',
            'Muzaffargarh City Hospital',
            'Green Valley Medical Store',
            'Care First Clinic',
            'Rangpur Health Complex',
            'Sanawan Family Pharmacy',
        ];

        foreach ($clients as $index => $name) {
            ClientLogo::updateOrCreate(
                ['name' => $name],
                ['sort_order' => $index + 1, 'is_visible' => true]
            );
        }
    }
}
