<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Features\FeatureAccessService;
use Database\Seeders\EnableMachineryEquipmentModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "please login this i need 100% same clone or features in our daftri
 * app accounting system everything" — Daftari already has the
 * Machinery & Equipment module (Rental Contracts, Hired-in Equipment,
 * Operators, per-machine cost/utilization, expiry reminders) built and
 * seeded with the real fleet; it was just hidden because the
 * company's plan doesn't include it. This flips the real company's own
 * override, not the shared plan.
 */
class EnableMachineryEquipmentModuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new EnableMachineryEquipmentModuleSeeder)->run();

        $this->assertSame(0, CompanyOverride::count());
    }

    public function test_it_enables_the_module_via_a_company_override_without_touching_the_plan(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        $plan = Plan::create(['name' => 'Business', 'slug' => 'business-'.uniqid(), 'has_machinery_equipment' => false, 'is_active' => true, 'is_public' => true]);
        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'yearly', 'current_period_start' => now()->subMonth(), 'current_period_end' => now()->addYear()]);

        $this->assertFalse(app(FeatureAccessService::class)->enabled($company, 'machinery_equipment'), 'the plan itself still has it off');

        (new EnableMachineryEquipmentModuleSeeder)->run();

        $this->assertTrue(app(FeatureAccessService::class)->enabled($company, 'machinery_equipment'));
        $plan->refresh();
        $this->assertFalse((bool) $plan->has_machinery_equipment, 'the shared plan row is never touched');
    }

    public function test_reseeding_updates_rather_than_duplicates_the_override(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new EnableMachineryEquipmentModuleSeeder)->run();
        (new EnableMachineryEquipmentModuleSeeder)->run();

        $this->assertSame(1, CompanyOverride::where('company_id', $company->id)->where('key', 'machinery_equipment')->count());
    }
}
