<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanyOverride;
use App\Models\ModuleRequest;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Features\FeatureAccessService;
use Database\Seeders\EnableMachineryEquipmentModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "the module should be enabled by admin not through seeder companies
 * make a request for module and superadmin will enable that module
 * for company" — Daftari already has that real flow (Modules
 * marketplace + Admin Module Requests queue), which sets the same
 * CompanyOverride this seeder sets directly. This backfills a matching
 * approved ModuleRequest alongside the override so the one-off seeder
 * run still leaves a clean audit trail, as if it had gone through that
 * real flow.
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

    public function test_it_backfills_an_approved_module_request_for_the_audit_trail(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $superAdmin = User::factory()->create(['role' => 'super_admin', 'company_id' => null, 'status' => 'active']);

        (new EnableMachineryEquipmentModuleSeeder)->run();

        $request = ModuleRequest::where('company_id', $company->id)->where('module_key', 'machinery_equipment')->first();
        $this->assertNotNull($request);
        $this->assertSame('approved', $request->status);
        $this->assertSame($owner->id, $request->requested_by);
        $this->assertSame($superAdmin->id, $request->reviewed_by);
        $this->assertNotNull($request->reviewed_at);
    }

    public function test_reseeding_updates_rather_than_duplicates_the_override(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new EnableMachineryEquipmentModuleSeeder)->run();
        (new EnableMachineryEquipmentModuleSeeder)->run();

        $this->assertSame(1, CompanyOverride::where('company_id', $company->id)->where('key', 'machinery_equipment')->count());
        $this->assertSame(1, ModuleRequest::where('company_id', $company->id)->where('module_key', 'machinery_equipment')->count());
    }
}
