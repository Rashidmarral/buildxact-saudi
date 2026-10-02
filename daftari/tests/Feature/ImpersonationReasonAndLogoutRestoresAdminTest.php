<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Security audit findings M-20 (no justification required or captured
 * when starting impersonation) and M-21 (the ordinary "log out" button
 * while impersonating fully destroyed the session instead of cleanly
 * returning to the real admin, with no stop_impersonate audit entry in
 * that path).
 */
class ImpersonationReasonAndLogoutRestoresAdminTest extends TestCase
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

    public function test_starting_impersonation_without_a_reason_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        [$company] = $this->makeCompanyWithOwner();

        $response = $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company));

        $response->assertSessionHasErrors('reason');
        $this->assertNull(session('impersonator_id'));
    }

    public function test_the_reason_is_recorded_on_the_impersonate_audit_entry(): void
    {
        $admin = $this->makeAdmin();
        [$company] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company), ['reason' => 'Debugging a support ticket']);

        $entry = AuditLog::where('action', 'company.impersonate')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertStringContainsString('Debugging a support ticket', $entry->description);
    }

    public function test_logging_out_while_impersonating_cleanly_returns_to_the_admin_session(): void
    {
        $admin = $this->makeAdmin();
        [$company, $owner] = $this->makeCompanyWithOwner();

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.companies.impersonate', $company), ['reason' => 'Support ticket #123']);

        $this->assertEquals($owner->id, auth()->id());

        $response = $this->post(route('logout'));

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertEquals($admin->id, auth()->id());
        $this->assertNull(session('impersonator_id'));

        $entry = AuditLog::where('action', 'company.stop_impersonate')->latest('id')->first();
        $this->assertNotNull($entry);
        $this->assertEquals($admin->id, $entry->admin_user_id);

        // A real "you were logged out" auth.logout entry must NOT also
        // have been recorded for this request — the admin's session is
        // still very much alive, just no longer impersonating.
        $this->assertSame(0, AuditLog::where('action', 'auth.logout')->count());
    }

    public function test_an_ordinary_logout_with_no_impersonation_in_progress_is_unaffected(): void
    {
        $owner = User::factory()->create(['role' => 'owner', 'status' => 'active']);

        $response = $this->actingAs($owner)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertNull(auth()->id());
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.logout', 'admin_user_id' => $owner->id]);
    }
}
