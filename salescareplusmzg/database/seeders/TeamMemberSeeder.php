<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use Illuminate\Database\Seeder;

class TeamMemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = [
            [
                'name' => 'Muhammad Sarfraz',
                'designation' => 'Chief Executive Officer',
                'bio' => 'Over 15 years in pharmaceutical distribution across South Punjab, leading Sales Care Plus MZG\'s growth from a single warehouse to a multi-district supply network.',
                'initials' => 'MS',
            ],
            [
                'name' => 'Sana Iqbal',
                'designation' => 'Head of Quality Assurance',
                'bio' => 'Oversees storage conditions, batch verification and cold-chain compliance to ensure every medicine that leaves our warehouse meets quality standards.',
                'initials' => 'SI',
            ],
            [
                'name' => 'Kashif Raza',
                'designation' => 'Logistics & Distribution Manager',
                'bio' => 'Coordinates our delivery fleet across Muzaffargarh and surrounding tehsils, keeping pharmacy shelves stocked with next-day fulfilment.',
                'initials' => 'KR',
            ],
            [
                'name' => 'Bushra Aslam',
                'designation' => 'Client Relations Manager',
                'bio' => 'The first point of contact for pharmacies and hospitals, handling orders, queries and account support with a personal touch.',
                'initials' => 'BA',
            ],
        ];

        foreach ($members as $index => $member) {
            TeamMember::updateOrCreate(
                ['name' => $member['name']],
                [
                    'designation' => $member['designation'],
                    'bio' => $member['bio'],
                    'initials' => $member['initials'],
                    'sort_order' => $index,
                ]
            );
        }
    }
}
