<?php

namespace Tests\Feature;

use App\Mail\NewLeadMail;
use App\Models\AuditLog;
use App\Models\CmsPage;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Phase 2 of the Sales, Compliance & Business Growth request: a real CRM
 * pipeline (Lead -> Contacted -> Qualified -> Demo -> Trial -> Setup ->
 * Paid -> Active -> Renewal/Lost) fed by every public lead-capture entry
 * point — /contact, /get-started, and the industry landing pages seeded
 * by IndustryLandingPageSeeder — plus the admin-side management of it.
 */
class LeadCrmTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_the_contact_form_also_creates_a_lead_without_a_duplicate_notification_email(): void
    {
        Mail::fake();
        Setting::set('support_email', 'support@example.test');

        $this->post(route('contact.submit'), [
            'name' => 'Prospective Customer',
            'email' => 'prospect@example.test',
            'message' => 'Do you support multi-branch VAT reporting?',
        ])->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'email' => 'prospect@example.test',
            'source' => 'contact_form',
            'status' => 'lead',
        ]);
        Mail::assertNotQueued(NewLeadMail::class);
    }

    public function test_get_started_creates_a_lead_and_notifies_the_sales_team(): void
    {
        Mail::fake();
        Setting::set('support_email', 'sales@example.test');

        $response = $this->post(route('get-started.submit'), [
            'name' => 'Ahmed Al-Rashid',
            'email' => 'ahmed@example.test',
            'phone' => '0501234567',
            'company_name' => 'Al-Rashid Trading',
            'industry' => 'trading',
            'source' => 'get_started',
            'message' => 'Interested in a demo.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('leads', [
            'email' => 'ahmed@example.test',
            'industry' => 'trading',
            'source' => 'get_started',
            'status' => 'lead',
        ]);
        Mail::assertQueued(NewLeadMail::class, fn ($mail) => $mail->hasTo('sales@example.test') && $mail->lead->email === 'ahmed@example.test');
    }

    public function test_get_started_rejects_an_invalid_industry(): void
    {
        $response = $this->post(route('get-started.submit'), [
            'name' => 'Test', 'email' => 'test@example.test', 'industry' => 'not-a-real-industry',
        ]);

        $response->assertSessionHasErrors('industry');
    }

    public function test_industry_landing_pages_are_seeded_and_reachable_with_a_get_started_link(): void
    {
        $this->seed(\Database\Seeders\IndustryLandingPageSeeder::class);

        $this->assertSame(6, CmsPage::where('slug', 'like', 'solutions-%')->count());

        $response = $this->get(route('cms-page.show', 'solutions-contracting'));
        $response->assertOk();
        $response->assertSee('get-started', false);
    }

    public function test_industry_landing_page_seeder_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\IndustryLandingPageSeeder::class);
        $this->seed(\Database\Seeders\IndustryLandingPageSeeder::class);

        $this->assertSame(6, CmsPage::where('slug', 'like', 'solutions-%')->count());
    }

    public function test_a_permitted_admin_can_view_and_filter_the_leads_list(): void
    {
        Lead::create(['name' => 'A', 'email' => 'a@example.test', 'status' => 'lead', 'industry' => 'retail', 'source' => 'other']);
        Lead::create(['name' => 'B', 'email' => 'b@example.test', 'status' => 'paid', 'industry' => 'retail', 'source' => 'other']);

        $response = $this->actingAs($this->makeSuperAdmin())->get(route('admin.leads.index', ['status' => 'paid']));

        $response->assertOk();
        $response->assertSee('B');
        $response->assertDontSee('A@example.test');
    }

    public function test_a_non_permitted_admin_cannot_reach_the_leads_crm(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $this->actingAs($staff)->get(route('admin.leads.index'))->assertForbidden();
    }

    public function test_an_admin_can_add_a_note_and_it_appears_in_the_timeline(): void
    {
        $lead = Lead::create(['name' => 'Sara', 'email' => 'sara@example.test', 'status' => 'lead', 'source' => 'other']);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post(route('admin.leads.note', $lead), ['body' => 'Called, left voicemail.'])
            ->assertRedirect();

        $this->assertDatabaseHas('lead_notes', ['lead_id' => $lead->id, 'body' => 'Called, left voicemail.']);

        $this->actingAs($admin)->get(route('admin.leads.show', $lead))->assertSee('Called, left voicemail.');
    }

    public function test_an_admin_can_move_a_lead_through_the_pipeline_and_it_is_audit_logged(): void
    {
        $lead = Lead::create(['name' => 'Khalid', 'email' => 'khalid@example.test', 'status' => 'lead', 'source' => 'other']);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post(route('admin.leads.status', $lead), ['status' => 'contacted'])->assertRedirect();

        $lead->refresh();
        $this->assertSame('contacted', $lead->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lead.status_change', 'subject_type' => Lead::class, 'subject_id' => $lead->id]);
    }

    public function test_marking_a_lead_lost_requires_a_reason(): void
    {
        $lead = Lead::create(['name' => 'Noura', 'email' => 'noura@example.test', 'status' => 'demo', 'source' => 'other']);

        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.leads.status', $lead), ['status' => 'lost']);

        $response->assertSessionHasErrors('lost_reason');
    }

    public function test_marking_a_lead_lost_with_a_reason_saves_it(): void
    {
        $lead = Lead::create(['name' => 'Noura', 'email' => 'noura@example.test', 'status' => 'demo', 'source' => 'other']);

        $this->actingAs($this->makeSuperAdmin())->post(route('admin.leads.status', $lead), [
            'status' => 'lost', 'lost_reason' => 'Chose a competitor.',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertTrue($lead->isLost());
        $this->assertSame('Chose a competitor.', $lead->lost_reason);
    }

    public function test_an_admin_can_assign_a_lead_to_another_permitted_admin(): void
    {
        $lead = Lead::create(['name' => 'Fahad', 'email' => 'fahad@example.test', 'status' => 'lead', 'source' => 'other']);
        $owner = $this->makeSuperAdmin();
        $assignee = $this->makeSuperAdmin();

        $this->actingAs($owner)->post(route('admin.leads.assign', $lead), ['assigned_admin_id' => $assignee->id])->assertRedirect();

        $this->assertSame($assignee->id, $lead->fresh()->assigned_admin_id);
    }

    public function test_an_admin_can_schedule_a_demo_and_follow_up(): void
    {
        $lead = Lead::create(['name' => 'Mona', 'email' => 'mona@example.test', 'status' => 'demo', 'source' => 'other']);

        $this->actingAs($this->makeSuperAdmin())->post(route('admin.leads.follow-up', $lead), [
            'demo_at' => '2026-10-01 10:00:00',
            'next_follow_up_at' => '2026-10-02 09:00:00',
        ])->assertRedirect();

        $lead->refresh();
        $this->assertNotNull($lead->demo_at);
        $this->assertNotNull($lead->next_follow_up_at);
    }

    public function test_an_admin_can_link_a_lead_to_a_converted_company_account(): void
    {
        $lead = Lead::create(['name' => 'Yousef', 'email' => 'yousef@example.test', 'status' => 'paid', 'source' => 'other']);
        $company = Company::create(['name' => 'Yousef Trading Co', 'slug' => 'yousef-trading-'.uniqid(), 'status' => 'active']);

        $this->actingAs($this->makeSuperAdmin())->post(route('admin.leads.link-company', $lead), [
            'converted_company_id' => $company->id,
        ])->assertRedirect();

        $this->assertSame($company->id, $lead->fresh()->converted_company_id);
    }

    public function test_won_stages_count_toward_conversion_and_lost_does_not(): void
    {
        $lead = Lead::create(['name' => 'Active Customer', 'email' => 'active@example.test', 'status' => 'active', 'source' => 'other']);
        $lostLead = Lead::create(['name' => 'Lost Deal', 'email' => 'lost@example.test', 'status' => 'lost', 'source' => 'other']);

        $this->assertTrue($lead->isWon());
        $this->assertFalse($lostLead->isWon());
        $this->assertTrue($lostLead->isLost());
    }
}
