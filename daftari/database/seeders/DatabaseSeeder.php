<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CurrencySeeder::class,
            PlanSeeder::class,
            AdminSeeder::class,
            AdminRoleSeeder::class,
            CmsContentSeeder::class,
        ]);

        // RealCompanySeeder deliberately isn't called here (Module 25 —
        // CodeCanyon release prep): it seeds one specific operator's real
        // company records (government CR/VAT numbers, a real bank IBAN,
        // working login credentials) for their own production instance.
        // Shipping that in a distributable package's default `migrate
        // --seed` would put a real person's business and banking details
        // into every buyer's database. Run it explicitly and only on the
        // operator's own instance if needed:
        // `php artisan db:seed --class=RealCompanySeeder`.

        if (app()->environment(['local', 'testing']) || env('SEED_DEMO', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
