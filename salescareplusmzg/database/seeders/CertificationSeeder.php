<?php

namespace Database\Seeders;

use App\Models\Certification;
use Illuminate\Database\Seeder;

class CertificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $certifications = [
            [
                'title' => 'DRAP Registered Distributor',
                'issuing_body' => 'Drug Regulatory Authority of Pakistan',
                'description' => 'Licensed to store, sell and distribute medicines under Pakistan\'s national drug regulations.',
                'icon' => 'badge-check',
            ],
            [
                'title' => 'Good Distribution Practices (GDP)',
                'issuing_body' => 'Internal Quality Management System',
                'description' => 'Warehousing, transport and cold-chain handling that follow GDP guidelines to protect product integrity.',
                'icon' => 'thermometer',
            ],
            [
                'title' => 'Punjab Drug License',
                'issuing_body' => 'Provincial Drug Control Department',
                'description' => 'Valid wholesale drug license covering our Muzaffargarh warehouse and distribution operations.',
                'icon' => 'file-check',
            ],
            [
                'title' => 'Member, Pakistan Chemists & Druggists Association',
                'issuing_body' => 'PCDA',
                'description' => 'Active member of the trade association upholding ethical distribution standards across Punjab.',
                'icon' => 'users',
            ],
        ];

        foreach ($certifications as $index => $certification) {
            Certification::updateOrCreate(
                ['title' => $certification['title']],
                [
                    'issuing_body' => $certification['issuing_body'],
                    'description' => $certification['description'],
                    'icon' => $certification['icon'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
