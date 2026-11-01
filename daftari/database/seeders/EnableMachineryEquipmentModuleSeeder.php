<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\CompanyOverride;
use Illuminate\Database\Seeder;

/**
 * "please login this i need 100% same clone or features in our daftri
 * app accounting system everything" / confirmed you rent equipment out
 * to others, and want per-machine cost history, maintenance reminders,
 * document expiry tracking, and operator assignment — comparing
 * Hamly's Rental Management and Fleet Management menus against Daftari
 * found Daftari already has all of this built: a Machinery & Equipment
 * module with Assets, Rental Contracts, Hired-in Equipment and
 * Operators, including per-machine revenue/cost/utilization
 * calculations and registration/insurance expiry reminders
 * (MachineryAsset, MachineryRentalContract, MachineryHireInContract —
 * see their own docblocks).
 *
 * It's just switched off: your real database already has your real
 * fleet of 7 machines seeded (Asphalt Paver, Steel Roller, Water
 * Tanker, Spray Tanker, Dump Truck, Pickup, Bobcat — "Added per fleet
 * list provided 2026-10-02"), but your company's plan ("Business")
 * has has_machinery_equipment = 0, so the module's nav links and
 * routes stay hidden and blocked (EnsureModuleEnabled middleware).
 *
 * Rather than change the plan itself (shared across every company on
 * this plan, not just yours), this sets the proper per-company
 * override FeatureAccessService already supports — the same mechanism
 * a Super Admin would use from Settings to unlock one feature for one
 * company without touching anyone else's plan.
 *
 * Run it yourself, after taking a database backup:
 *   php artisan db:seed --class="Database\\Seeders\\EnableMachineryEquipmentModuleSeeder"
 *
 * Idempotent: updates the existing override if one's already there,
 * otherwise creates it.
 */
class EnableMachineryEquipmentModuleSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        CompanyOverride::updateOrCreate(
            ['company_id' => $company->id, 'type' => 'feature', 'key' => 'machinery_equipment'],
            ['value' => '1', 'reason' => 'Operator confirmed real use: rents equipment out to others, tracks fleet cost/maintenance/documents — the module was already built with their real 7-machine fleet seeded but hidden by the plan.']
        );

        $this->command?->info('Machinery & Equipment module enabled for '.$company->name.' — Assets, Rental Contracts, Hired-in Equipment and Operators now show in the nav.');
    }
}
