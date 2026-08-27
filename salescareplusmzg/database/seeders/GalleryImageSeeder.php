<?php

namespace Database\Seeders;

use App\Models\GalleryImage;
use Illuminate\Database\Seeder;

class GalleryImageSeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            ['illustration' => 'warehouse', 'title' => 'Our Warehouse', 'caption' => 'GDP-compliant storage in Muzaffargarh, organised by category and batch.'],
            ['illustration' => 'cold-chain', 'title' => 'Cold-Chain Storage', 'caption' => 'Monitored temperature-controlled units for sensitive medicines.'],
            ['illustration' => 'delivery', 'title' => 'Delivery Fleet', 'caption' => 'Daily routes covering Muzaffargarh, Alipur, Kot Addu and Jatoi.'],
            ['illustration' => 'medicines', 'title' => 'Medicine Handling', 'caption' => 'Careful picking and packing across our full product catalogue.'],
            ['illustration' => 'pharmacist', 'title' => 'Quality Checks', 'caption' => 'Batch verification and quality assurance before every dispatch.'],
            ['illustration' => 'team', 'title' => 'Our Team', 'caption' => 'The people behind every order, from warehouse to doorstep.'],
        ];

        foreach ($images as $index => $image) {
            GalleryImage::updateOrCreate(
                ['title' => $image['title']],
                [
                    'caption' => $image['caption'],
                    'illustration' => $image['illustration'],
                    'sort_order' => $index + 1,
                    'is_visible' => true,
                ]
            );
        }
    }
}
