<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ProductCategorySeeder::class,
            ProductSeeder::class,
            PrincipalSeeder::class,
            TestimonialSeeder::class,
            CertificationSeeder::class,
            TeamMemberSeeder::class,
            FaqSeeder::class,
            GalleryImageSeeder::class,
            ContentItemSeeder::class,
            ClientLogoSeeder::class,
            LegalPageSeeder::class,
            AdminUserSeeder::class,
            SettingSeeder::class,
            NavItemSeeder::class,
        ]);
    }
}
