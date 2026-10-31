<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug found while investigating a user's report: they'd synced several
 * test invoices to ZATCA Simulation, then deleted those invoices directly
 * in the database (bypassing Invoice::destroy()'s isZatcaLocked() guard,
 * which exists specifically to prevent this) to free up the numbers for
 * real invoices. That emptied zatca_invoice_logs, so
 * ZatcaSyncService::nextIcv() — a bare count of that table — would hand
 * their next real submission ICV=1, while ZATCA's own Simulation server
 * still has their real submission history on file expecting the next ICV
 * to continue from there. Likely rejection, not a security hole (this is
 * Simulation), but exactly the mess "Reset onboarding" should let someone
 * cleanly recover from — except it didn't: it cleared CSR/CSIDs but left
 * zatca_last_invoice_hash in place, so a fresh CSID's first invoice would
 * carry a PIH pointing at a hash chain belonging to a now-discarded
 * identity instead of the ZATCA-defined genesis hash.
 *
 * Both the company-owner-facing reset (App\Http\Controllers\User\
 * ZatcaController::resetOnboarding) and the Super Admin support
 * equivalent (App\Http\Controllers\Admin\ZatcaController::resetOnboarding)
 * now also clear zatca_last_invoice_hash/zatca_last_sync_at AND
 * zatca_icv_counter, so a reset genuinely starts the next invoice from
 * ICV 1 and the genesis hash together — see
 * ZatcaIcvScopedPerEnvironmentTest for why the counter itself had to
 * move off a cross-environment COUNT(*) to make that possible at all.
 */
class ZatcaOnboardingResetClearsHashChainTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompanyAndOwner(): array
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_zatca_phase2' => true,
        ]);
        $company = Company::create([
            'name' => 'Reset Co.', 'slug' => 'reset-co-'.uniqid(), 'status' => 'active', 'currency' => 'SAR',
            'zatca_onboarding_status' => 'onboarded', 'zatca_production_csid' => 'fake-csid',
            'zatca_last_invoice_hash' => 'hash-of-a-now-deleted-test-invoice',
            'zatca_last_sync_at' => now(), 'zatca_icv_counter' => 5,
        ]);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['company_id' => $company->id, 'role' => 'owner', 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_the_owner_facing_reset_clears_the_hash_chain(): void
    {
        [$company, $owner] = $this->makeCompanyAndOwner();

        $this->actingAs($owner)->post(route('app.zatca.reset'))->assertRedirect();

        $company->refresh();
        $this->assertNull($company->zatca_last_invoice_hash);
        $this->assertNull($company->zatca_last_sync_at);
        $this->assertSame('not_started', $company->zatca_onboarding_status);
        $this->assertSame(0, $company->zatca_icv_counter);
    }

    public function test_the_admin_support_reset_clears_the_hash_chain(): void
    {
        [$company] = $this->makeCompanyAndOwner();
        $admin = User::factory()->create(['role' => 'super_admin', 'company_id' => null]);

        $this->actingAs($admin)
            ->withSession(['auth.password_confirmed_at' => now()->timestamp])
            ->post(route('admin.zatca.companies.reset-onboarding', $company))
            ->assertRedirect();

        $company->refresh();
        $this->assertNull($company->zatca_last_invoice_hash);
        $this->assertNull($company->zatca_last_sync_at);
        $this->assertSame(0, $company->zatca_icv_counter);
    }
}
