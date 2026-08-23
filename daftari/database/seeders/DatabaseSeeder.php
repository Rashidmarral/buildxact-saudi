<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            AdminSeeder::class,
            AdminRoleSeeder::class,
            RealCompanySeeder::class,
        ]);

        if (app()->environment(['local', 'testing']) || env('SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
