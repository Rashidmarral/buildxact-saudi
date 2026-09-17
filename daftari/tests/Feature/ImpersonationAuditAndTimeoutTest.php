<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding MEDIUM-18: impersonating a company (Admin\CompanyController
 * ::impersonate()) swaps Auth::user() to the tenant owner for the rest of
 * the session, so any AuditLog::record() call made without an explicit
 * $actorId during that window used to attribute the action to the tenant
 * owner instead of the admin actually behind the keyboard — and nothing
 * ever ended a stale impersonation session on its own.
 */
class ImpersonationAuditAndTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null, 'status' => 'active']);
    }

    private function makeCompanyWithOwner(): array
    {
        $company = Company::create(['name' => 'Impersonation Co.', 'slug' => 'impersonation-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_actions_taken_while_impersonating_are_attributed_to_the_admin_not_the_tenant_owner(): void
    {
        $admin = $this->makeAdmin();
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company));

        $this->assertEquals($admin->id, session('impersonator_id'));
        $this->assertNotNull(session('impersonation_started_at'));

        // While impersonating, Auth::user() resolves to the tenant owner.
        // A plain AuditLog::record() call with no explicit actor (as every
        // ordinary controller action makes) must still land on the admin.
        $entry = AuditLog::record('client.created', $company, 'Created a client while impersonating');

        $this->assertEquals($admin->id, $entry->admin_user_id);
        $this->assertEquals($owner->id, $entry->impersonated_user_id);
        $this->assertEquals($company->id, $entry->company_id);
    }

    public function test_the_impersonate_start_entry_itself_is_still_attributed_to_the_admin(): void
    {
        $admin = $this->makeAdmin();
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company));

        $entry = AuditLog::where('action', 'company.impersonate')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertEquals($admin->id, $entry->admin_user_id);
        $this->assertNull($entry->impersonated_user_id);
    }

    public function test_stopping_impersonation_still_attributes_to_the_admin_via_explicit_actor_id(): void
    {
        $admin = $this->makeAdmin();
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company));
        $this->post(route('stop-impersonating'));

        $entry = AuditLog::where('action', 'company.stop_impersonate')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertEquals($admin->id, $entry->admin_user_id);
        $this->assertNull(session('impersonator_id'));
        $this->assertNull(session('impersonation_started_at'));
    }

    public function test_an_impersonation_session_older_than_the_timeout_is_ended_automatically(): void
    {
        $admin = $this->makeAdmin();
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company));

        session(['impersonation_started_at' => now()->subMinutes(61)->timestamp]);

        $response = $this->get(route('app.dashboard'));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertNull(session('impersonator_id'));
        $this->assertNull(session('impersonation_started_at'));
        $this->assertEquals($admin->id, auth()->id());

        $entry = AuditLog::where('action', 'company.impersonation_expired')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertEquals($admin->id, $entry->admin_user_id);
        $this->assertEquals($owner->id, $entry->impersonated_user_id);
    }

    public function test_an_impersonation_session_within_the_timeout_is_left_alone(): void
    {
        $admin = $this->makeAdmin();
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company));

        session(['impersonation_started_at' => now()->subMinutes(10)->timestamp]);

        $response = $this->get(route('app.dashboard'));

        $response->assertOk();
        $this->assertEquals($admin->id, session('impersonator_id'));
        $this->assertEquals($owner->id, auth()->id());
    }
}
