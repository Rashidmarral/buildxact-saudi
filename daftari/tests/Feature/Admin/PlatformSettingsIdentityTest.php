<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The operator was confused into thinking "bill subscriptions through this
 * company" was something they had to re-pick per payment, and didn't know
 * whether subscription invoices needed a separate Phase 2 sync setup from
 * their already-onboarded contracting business. This page now makes both
 * explicit: a "One-time setup" label on the field, and a status box that
 * shows the currently selected company's real ZATCA onboarding state with
 * direct links to its ZATCA dashboard and VAT report.
 */
class PlatformSettingsIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_no_status_box_when_no_billing_company_is_configured(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get(route('admin.settings.edit'));

        $response->assertOk()
            ->assertSee(__('One-time setup'))
            ->assertDontSee(__('Currently billing subscriptions through'));
    }

    public function test_shows_not_yet_onboarded_guidance_for_a_company_without_zatca_phase2(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid()]);
        Setting::set('platform_billing_company_id', $company->id);

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.settings.edit'));

        $response->assertOk()
            ->assertSee($company->name)
            ->assertSee(__('This company is not yet ZATCA Phase 2 onboarded, so subscription invoices currently get a Phase 1 QR code only. Complete onboarding for this company (once) to get Phase 2 XML for subscription invoices too — the same sync will then cover them alongside its other invoices automatically.'))
            ->assertSee(route('admin.zatca.index', ['q' => $company->name]), false)
            ->assertSee(route('admin.reports.vat', ['company_id' => $company->id]), false);
    }

    public function test_shows_the_already_onboarded_reassurance_when_the_company_is_zatca_phase2_ready(): void
    {
        $company = Company::create([
            'name' => 'Dynamic Core Contracting Company',
            'slug' => 'dcc-'.uniqid(),
            'zatca_onboarding_status' => 'onboarded',
            'zatca_production_csid' => 'csid-123',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
        ]);
        Setting::set('platform_billing_company_id', $company->id);

        $this->assertTrue($company->isZatcaOnboarded());

        $response = $this->actingAs($this->makeAdmin())->get(route('admin.settings.edit'));

        $response->assertOk()->assertSee(__('ZATCA Phase 2 is already onboarded for this company — subscription invoices sync for Phase 2 automatically alongside its other invoices. No separate setup needed.'));
    }
}
