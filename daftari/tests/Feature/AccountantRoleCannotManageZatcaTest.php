<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Security audit finding M-08: the default "Accountant" role preset
 * carried the coarse 'zatca' permission, which gates not just viewing
 * compliance status but also CSR generation, CSID issuance, and
 * onboarding reset — any of which can take down live e-invoicing. A
 * bookkeeping role has no routine need to touch that.
 */
class AccountantRoleCannotManageZatcaTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        return Company::create(['name' => 'Acme', 'slug' => 'acme-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
    }

    public function test_the_seeded_accountant_preset_no_longer_grants_zatca(): void
    {
        $company = $this->makeCompany();
        Role::seedSystemRoles($company->id);

        $accountant = Role::where('company_id', $company->id)->where('slug', 'accountant')->first();

        $this->assertNotNull($accountant);
        $this->assertNotContains('zatca', $accountant->permissions);
    }

    public function test_a_staff_member_with_only_the_accountant_role_cannot_reach_zatca_pages(): void
    {
        $company = $this->makeCompany();
        Role::seedSystemRoles($company->id);
        $accountant = Role::where('company_id', $company->id)->where('slug', 'accountant')->first();

        $staff = User::factory()->create(['company_id' => $company->id, 'role' => 'staff', 'status' => 'active']);
        $staff->roles()->attach($accountant->id);

        $this->actingAs($staff)->get(route('app.zatca.dashboard'))->assertForbidden();
        $this->actingAs($staff)->post(route('app.zatca.reset'))->assertForbidden();
    }

    public function test_the_backfill_migration_strips_zatca_from_an_already_seeded_accountant_role(): void
    {
        $company = $this->makeCompany();

        // Simulate a role seeded before this fix — bypasses the current
        // (already-fixed) preset to reproduce the pre-fix seeded shape.
        DB::table('roles')->insert([
            'company_id' => $company->id, 'name' => 'Accountant', 'slug' => 'accountant', 'is_system' => true,
            'permissions' => json_encode(['dashboard', 'invoices', 'zatca', 'accounting']),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_09_20_010000_remove_zatca_permission_from_seeded_accountant_roles.php');
        $migration->up();

        $accountant = Role::where('company_id', $company->id)->where('slug', 'accountant')->first();
        $this->assertNotContains('zatca', $accountant->permissions);
        $this->assertContains('dashboard', $accountant->permissions);
        $this->assertContains('invoices', $accountant->permissions);
        $this->assertContains('accounting', $accountant->permissions);
    }
}
