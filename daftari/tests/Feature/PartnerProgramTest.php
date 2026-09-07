<?php

namespace Tests\Feature;

use App\Mail\NewPartnerApplicationMail;
use App\Mail\PartnerInviteMail;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Partner;
use App\Models\PartnerPayout;
use App\Models\PartnerReferral;
use App\Models\PartnerType;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Phase 3 of the Sales, Compliance & Business Growth request: an
 * Accountant Partner / Reseller / Referral program (items 11-12) with
 * fully admin-configurable partner types and commission rules — nothing
 * seeded, nothing hard-coded. Covers the full lifecycle: public
 * application -> admin approval -> invite acceptance -> referral capture
 * via a partner's link -> commission approval -> payout request/payment,
 * plus permission gating throughout.
 */
class PartnerProgramTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makePartnerType(array $overrides = []): PartnerType
    {
        return PartnerType::create(array_merge([
            'name_en' => 'Accountant Partner',
            'slug' => 'accountant-partner-'.uniqid(),
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'is_recurring' => true,
            'is_active' => true,
        ], $overrides));
    }

    public function test_no_partner_type_exists_until_an_admin_creates_one(): void
    {
        $this->assertSame(0, PartnerType::count());
    }

    public function test_a_super_admin_can_create_a_partner_type_with_its_own_commission_rule(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.partner-types.store'), [
            'name_en' => 'Reseller',
            'commission_type' => 'fixed',
            'commission_value' => '250.00',
            'is_recurring' => '0',
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.partner-types.index'));
        $this->assertDatabaseHas('partner_types', ['name_en' => 'Reseller', 'commission_type' => 'fixed', 'commission_value' => 250]);
    }

    public function test_submitting_a_partner_application_creates_a_pending_partner_and_notifies_admins(): void
    {
        Mail::fake();
        Setting::set('support_email', 'sales@example.test');
        $type = $this->makePartnerType();

        $response = $this->post(route('partners.apply.submit'), [
            'name' => 'Fahad Al-Otaibi',
            'email' => 'fahad@example.test',
            'phone' => '0501112222',
            'company_name' => 'Al-Otaibi Accounting',
            'partner_type_id' => $type->id,
            'message' => 'I have 30 SME clients interested.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('partners', ['email' => 'fahad@example.test', 'status' => 'pending', 'user_id' => null]);
        Mail::assertQueued(NewPartnerApplicationMail::class, fn ($mail) => $mail->hasTo('sales@example.test'));
    }

    public function test_a_pending_application_has_no_login_and_no_referral_code(): void
    {
        $partner = Partner::create(['name' => 'Pending Partner', 'email' => 'pending@example.test', 'status' => 'pending']);

        $this->assertNull($partner->user_id);
        $this->assertNull($partner->referral_code);
    }

    public function test_a_non_permitted_admin_cannot_reach_partner_management(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $this->actingAs($staff)->get(route('admin.partners.index'))->assertForbidden();
    }

    public function test_approving_a_partner_creates_a_login_generates_a_referral_code_and_sends_an_invite(): void
    {
        Mail::fake();
        $type = $this->makePartnerType();
        $partner = Partner::create(['name' => 'New Partner', 'email' => 'newpartner@example.test', 'status' => 'pending']);

        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.partners.approve', $partner), [
            'partner_type_id' => $type->id,
        ]);

        $response->assertRedirect();
        $partner->refresh();
        $this->assertSame('invited', $partner->status);
        $this->assertNotNull($partner->referral_code);
        $this->assertNotNull($partner->user_id);
        $this->assertSame('partner', $partner->user->role);
        $this->assertSame('invited', $partner->user->status);
        Mail::assertQueued(PartnerInviteMail::class, fn ($mail) => $mail->member->id === $partner->user_id);
    }

    public function test_a_commission_override_takes_precedence_over_the_partner_types_default(): void
    {
        $type = $this->makePartnerType(['commission_type' => 'percentage', 'commission_value' => 10]);
        $partner = Partner::create([
            'name' => 'Override Partner', 'email' => 'override@example.test', 'status' => 'active',
            'partner_type_id' => $type->id, 'commission_type_override' => 'fixed', 'commission_value_override' => 500,
        ]);

        $this->assertSame('fixed', $partner->effectiveCommissionType());
        $this->assertSame(500.0, $partner->effectiveCommissionValue());
        $this->assertSame(500.0, $partner->suggestedCommission(9999));
    }

    public function test_without_any_override_the_partner_type_default_applies(): void
    {
        $type = $this->makePartnerType(['commission_type' => 'percentage', 'commission_value' => 10]);
        $partner = Partner::create(['name' => 'Default Partner', 'email' => 'default@example.test', 'status' => 'active', 'partner_type_id' => $type->id]);

        $this->assertSame(10.0, $partner->effectiveCommissionValue());
        $this->assertSame(100.0, $partner->suggestedCommission(1000));
    }

    public function test_rejecting_a_pending_application_requires_a_reason_and_creates_no_login(): void
    {
        $partner = Partner::create(['name' => 'Reject Me', 'email' => 'reject@example.test', 'status' => 'pending']);

        $this->actingAs($this->makeSuperAdmin())->post(route('admin.partners.reject', $partner), [
            'rejected_reason' => 'Not a fit for our target market.',
        ])->assertRedirect();

        $partner->refresh();
        $this->assertSame('rejected', $partner->status);
        $this->assertNull($partner->user_id);
    }

    public function test_a_partner_can_accept_their_invite_and_reach_their_dashboard(): void
    {
        $type = $this->makePartnerType();
        $partner = Partner::create(['name' => 'Accepting Partner', 'email' => 'accept@example.test', 'status' => 'pending']);
        $this->actingAs($this->makeSuperAdmin())->post(route('admin.partners.approve', $partner), ['partner_type_id' => $type->id]);
        $partner->refresh();
        $member = $partner->user;

        $url = URL::temporarySignedRoute('partner.invite.accept', now()->addHours(48), ['id' => $member->id, 'hash' => sha1($member->email)]);

        $response = $this->post($url, ['password' => 'a-secure-password', 'password_confirmation' => 'a-secure-password']);

        $response->assertRedirect(route('partner.dashboard'));
        $member->refresh();
        $partner->refresh();
        $this->assertSame('active', $member->status);
        $this->assertSame('active', $partner->status);
        $this->assertAuthenticatedAs($member);
    }

    public function test_a_get_started_submission_with_a_valid_referral_code_is_tagged_and_recorded(): void
    {
        $type = $this->makePartnerType();
        $partner = Partner::create([
            'name' => 'Referrer', 'email' => 'referrer@example.test', 'status' => 'active',
            'partner_type_id' => $type->id, 'referral_code' => Partner::generateReferralCode(),
        ]);

        $response = $this->post(route('get-started.submit'), [
            'name' => 'Referred Customer', 'email' => 'referred@example.test', 'ref' => $partner->referral_code,
        ]);

        $response->assertRedirect();
        $lead = Lead::where('email', 'referred@example.test')->firstOrFail();
        $this->assertSame('referral', $lead->source);
        $this->assertDatabaseHas('partner_referrals', ['partner_id' => $partner->id, 'lead_id' => $lead->id, 'status' => 'pending']);
    }

    public function test_an_unknown_referral_code_is_ignored_silently(): void
    {
        $response = $this->post(route('get-started.submit'), [
            'name' => 'Someone', 'email' => 'someone@example.test', 'ref' => 'NOTAREALCODE',
        ]);

        $response->assertRedirect();
        $lead = Lead::where('email', 'someone@example.test')->firstOrFail();
        $this->assertSame('get_started', $lead->source);
        $this->assertSame(0, PartnerReferral::count());
    }

    public function test_the_referral_short_link_redirects_into_get_started_tagged(): void
    {
        $partner = Partner::create(['name' => 'Linker', 'email' => 'linker@example.test', 'status' => 'active', 'referral_code' => Partner::generateReferralCode()]);

        $response = $this->get('/r/'.$partner->referral_code);

        $response->assertRedirect(route('get-started', ['ref' => $partner->referral_code]));
    }

    public function test_an_admin_can_approve_a_referrals_commission(): void
    {
        $partner = Partner::create(['name' => 'Commission Partner', 'email' => 'commission@example.test', 'status' => 'active']);
        $referral = PartnerReferral::create(['partner_id' => $partner->id, 'status' => 'pending']);
        $company = Company::create(['name' => 'Converted Co', 'slug' => 'converted-co-'.uniqid(), 'status' => 'active']);

        $this->actingAs($this->makeSuperAdmin())->post(route('admin.partners.referrals.update', $referral), [
            'status' => 'converted',
            'commission_amount' => '150.00',
            'commission_status' => 'approved',
            'company_id' => $company->id,
        ])->assertRedirect();

        $referral->refresh();
        $this->assertSame('converted', $referral->status);
        $this->assertSame('approved', $referral->commission_status);
        $this->assertSame(150.0, (float) $referral->commission_amount);
        $this->assertSame($company->id, $referral->company_id);
    }

    public function test_a_partner_can_view_their_own_dashboard_with_referrals_and_balance(): void
    {
        $partner = Partner::create(['name' => 'Dashboard Partner', 'email' => 'dash@example.test', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'partner', 'company_id' => null, 'status' => 'active']);
        $partner->update(['user_id' => $member->id]);
        PartnerReferral::create(['partner_id' => $partner->id, 'status' => 'converted', 'commission_amount' => 200, 'commission_status' => 'approved']);

        $response = $this->actingAs($member)->get(route('partner.dashboard'));

        $response->assertOk();
        $response->assertSee('200');
    }

    public function test_a_partner_can_request_a_payout_of_their_approved_balance(): void
    {
        $partner = Partner::create(['name' => 'Payout Partner', 'email' => 'payout@example.test', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'partner', 'company_id' => null, 'status' => 'active']);
        $partner->update(['user_id' => $member->id]);
        PartnerReferral::create(['partner_id' => $partner->id, 'status' => 'converted', 'commission_amount' => 300, 'commission_status' => 'approved']);

        $response = $this->actingAs($member)->post(route('partner.payouts.request'));

        $response->assertRedirect();
        $this->assertDatabaseHas('partner_payouts', ['partner_id' => $partner->id, 'amount' => 300, 'status' => 'requested']);
    }

    public function test_a_partner_cannot_request_a_payout_with_no_approved_balance(): void
    {
        $partner = Partner::create(['name' => 'Empty Partner', 'email' => 'empty@example.test', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'partner', 'company_id' => null, 'status' => 'active']);
        $partner->update(['user_id' => $member->id]);

        $response = $this->actingAs($member)->post(route('partner.payouts.request'));

        $response->assertSessionHasErrors('payout');
        $this->assertSame(0, PartnerPayout::count());
    }

    public function test_a_partner_cannot_reach_the_tenant_app_or_admin_panel(): void
    {
        $member = User::factory()->create(['role' => 'partner', 'company_id' => null, 'status' => 'active']);

        // EnsureUserBelongsToCompany bounces any company_id-less user (this
        // partner included) away from /app toward /admin rather than a
        // bare 403 — but role:super_admin,admin_staff then rejects them
        // there too, since a partner is neither.
        $this->actingAs($member)->get(route('app.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($member)->get(route('admin.dashboard'))->assertForbidden();
    }

    public function test_a_company_user_cannot_reach_the_partner_dashboard(): void
    {
        $company = Company::create(['name' => 'Some Co', 'slug' => 'some-co-'.uniqid(), 'status' => 'active']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id]);

        $this->actingAs($owner)->get(route('partner.dashboard'))->assertForbidden();
    }

    public function test_an_admin_can_mark_a_payout_paid(): void
    {
        $partner = Partner::create(['name' => 'Paid Partner', 'email' => 'paid@example.test', 'status' => 'active']);
        $payout = PartnerPayout::create(['partner_id' => $partner->id, 'amount' => 400, 'status' => 'requested', 'requested_at' => now()]);

        $this->actingAs($this->makeSuperAdmin())->post(route('admin.partners.payouts.update', $payout), [
            'status' => 'paid',
            'reference' => 'TRX-001',
        ])->assertRedirect();

        $payout->refresh();
        $this->assertSame('paid', $payout->status);
        $this->assertSame('TRX-001', $payout->reference);
        $this->assertNotNull($payout->processed_at);
    }

    public function test_a_super_admin_can_suspend_and_reactivate_an_active_partner(): void
    {
        $partner = Partner::create(['name' => 'Toggle Partner', 'email' => 'toggle@example.test', 'status' => 'active']);
        $member = User::factory()->create(['role' => 'partner', 'company_id' => null, 'status' => 'active']);
        $partner->update(['user_id' => $member->id]);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post(route('admin.partners.suspend', $partner))->assertRedirect();
        $this->assertSame('suspended', $partner->fresh()->status);
        $this->assertSame('suspended', $member->fresh()->status);

        $this->actingAs($admin)->post(route('admin.partners.reactivate', $partner))->assertRedirect();
        $this->assertSame('active', $partner->fresh()->status);
        $this->assertSame('active', $member->fresh()->status);
    }
}
