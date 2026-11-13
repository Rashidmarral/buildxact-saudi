<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit finding CRIT-04: the seeded "Read-only auditor" platform
 * admin role granted every permission key as a bare (full) grant, and
 * EnsureAdminPermission never distinguished a read action from a write
 * one — so an account meant to be read-only could suspend companies,
 * refund real payments, and change plans. Fixed by splitting every
 * permission check into 'view' (satisfied by a view-only or full grant)
 * and 'manage' (only a full grant) levels, and re-seeding "Read-only
 * auditor" with view-only (":view"-suffixed) keys.
 */
class ReadOnlyAdminRoleCannotMutateTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        return Company::create(array_merge([
            'name' => 'Acme Trading', 'slug' => 'acme-'.uniqid(), 'status' => 'active',
        ], $overrides));
    }

    private function makeReadOnlyAuditor(): User
    {
        AdminRole::seedSystemRoles();
        $role = AdminRole::where('slug', 'read_only')->firstOrFail();
        $user = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);
        $user->adminRoles()->attach($role);

        return $user;
    }

    private function withConfirmedPassword($user)
    {
        return $this->actingAs($user)->withSession(['auth.password_confirmed_at' => now()->timestamp]);
    }

    public function test_the_seeded_read_only_role_grants_view_only_keys(): void
    {
        AdminRole::seedSystemRoles();
        $role = AdminRole::where('slug', 'read_only')->firstOrFail();

        $this->assertNotEmpty($role->permissions);
        foreach ($role->permissions as $key) {
            $this->assertStringEndsWith(':view', $key, "Read-only auditor permission '{$key}' must be view-only.");
        }
    }

    public function test_a_read_only_auditor_can_view_the_company_list_and_a_single_company(): void
    {
        $auditor = $this->makeReadOnlyAuditor();
        $company = $this->makeCompany();

        $this->actingAs($auditor)->get(route('admin.companies.index'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.companies.show', $company))->assertOk();
    }

    public function test_a_read_only_auditor_cannot_suspend_a_company(): void
    {
        $auditor = $this->makeReadOnlyAuditor();
        $company = $this->makeCompany();

        $response = $this->withConfirmedPassword($auditor)->post(route('admin.companies.suspend', $company));

        $response->assertForbidden();
        $this->assertSame('active', $company->fresh()->status);
    }

    public function test_a_read_only_auditor_cannot_change_a_companys_plan(): void
    {
        $auditor = $this->makeReadOnlyAuditor();
        $company = $this->makeCompany();
        $plan = Plan::create(['name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true]);

        $response = $this->withConfirmedPassword($auditor)
            ->post(route('admin.companies.change-plan', $company), ['plan_id' => $plan->id]);

        $response->assertForbidden();
    }

    public function test_a_read_only_auditor_can_view_payments_but_cannot_refund_one(): void
    {
        $auditor = $this->makeReadOnlyAuditor();

        $this->actingAs($auditor)->get(route('admin.payments.index'))->assertOk();

        // Middleware runs before the controller resolves the route-bound
        // payment, so a nonexistent id is enough to prove the 403 comes
        // from the permission check, not a 404 for a missing record.
        $response = $this->withConfirmedPassword($auditor)->post(route('admin.payments.refund', 999999));
        $response->assertForbidden();
    }

    public function test_a_read_only_auditor_can_view_tickets_but_cannot_reply_to_one(): void
    {
        $auditor = $this->makeReadOnlyAuditor();
        $company = $this->makeCompany();
        $user = User::factory()->create(['company_id' => $company->id, 'role' => 'owner']);
        $ticket = Ticket::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'subject' => 'Help', 'description' => 'Something is broken.', 'status' => 'open', 'priority' => 'normal',
        ]);

        $this->actingAs($auditor)->get(route('admin.tickets.index'))->assertOk();
        $this->actingAs($auditor)->get(route('admin.tickets.show', $ticket))->assertOk();

        $response = $this->withConfirmedPassword($auditor)
            ->post(route('admin.tickets.reply', $ticket), ['body' => 'Reply']);
        $response->assertForbidden();
    }

    public function test_a_read_only_auditor_cannot_create_a_plan_or_a_coupon(): void
    {
        $auditor = $this->makeReadOnlyAuditor();

        $this->actingAs($auditor)->get(route('admin.plans.index'))->assertOk();

        $response = $this->actingAs($auditor)->post(route('admin.plans.store'), [
            'name' => 'New Plan', 'slug' => 'new-plan-'.uniqid(), 'price_monthly' => 10, 'price_yearly' => 100,
        ]);
        $response->assertForbidden();
    }

    public function test_the_support_preset_keeps_full_manage_access_to_its_own_sections_unaffected_by_this_fix(): void
    {
        AdminRole::seedSystemRoles();
        $role = AdminRole::where('slug', 'support')->firstOrFail();
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);
        $staff->adminRoles()->attach($role);
        $company = $this->makeCompany();

        $response = $this->withConfirmedPassword($staff)->post(route('admin.companies.suspend', $company));

        $response->assertRedirect();
        $this->assertSame('suspended', $company->fresh()->status);
    }

    /**
     * AdminRoleSeeder uses firstOrCreate(), so an already-installed
     * deployment's 'read_only' row keeps its original (over-privileged,
     * bare-key) permissions forever unless something updates it in place
     * — this is exactly what the 2026_09_17_040000 migration does.
     */
    public function test_the_backfill_migration_fixes_an_already_seeded_read_only_role_in_place(): void
    {
        $role = AdminRole::create([
            'name' => 'Read-only auditor', 'slug' => 'read_only', 'is_system' => true,
            'permissions' => ['companies', 'payments', 'zatca'],
        ]);

        $migration = require database_path('migrations/2026_09_17_040000_fix_read_only_admin_role_to_view_only_permissions.php');
        $migration->up();

        $role->refresh();
        foreach ($role->permissions as $key) {
            $this->assertStringEndsWith(':view', $key);
        }
    }
}
