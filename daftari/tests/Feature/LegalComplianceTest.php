<?php

namespace Tests\Feature;

use App\Models\LegalDocument;
use App\Models\PlatformActivity;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\LegalDocumentSeeder;
use Database\Seeders\PlatformIdentitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Requested: platform legal/business identity settings, admin-editable
 * legal document templates, and an admin checklist that verifies (without
 * fabricating) whether the operator's registered activities/licenses and
 * ZATCA certification status support selling this SaaS. Covers the
 * PlatformIdentitySeeder/LegalDocumentSeeder content, the CR-activity
 * software-suitability flag, the ZATCA-status toggle defaulting to
 * "not verified", and that only a super admin can manage any of it.
 */
class LegalComplianceTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_platform_identity_seeder_sets_the_operators_real_details_not_the_dev_placeholder(): void
    {
        Setting::set('platform_name', 'Daftari SA');
        Setting::set('platform_vat_number', '300012345600003');

        $this->seed(PlatformIdentitySeeder::class);

        $this->assertSame('Dynamic Core Contracting Company', Setting::get('platform_name'));
        $this->assertSame('314526094900003', Setting::get('platform_vat_number'));
        $this->assertSame('7053180563', Setting::get('platform_cr_number'));
    }

    public function test_platform_identity_seeder_does_not_overwrite_an_admins_own_customization(): void
    {
        Setting::set('platform_name', 'A Real Admin Typed This In');

        $this->seed(PlatformIdentitySeeder::class);

        $this->assertSame('A Real Admin Typed This In', Setting::get('platform_name'));
    }

    public function test_none_of_the_seeded_cr_activities_look_like_they_cover_software_sales(): void
    {
        $this->seed(PlatformIdentitySeeder::class);

        $this->assertGreaterThan(0, PlatformActivity::count());
        $this->assertFalse(PlatformActivity::seemsToCoverSoftwareSales());
    }

    public function test_adding_an_it_activity_flips_the_software_suitability_flag(): void
    {
        PlatformActivity::create(['code' => '620000', 'description_ar' => 'أنشطة البرمجيات وتطوير التطبيقات', 'sort_order' => 1]);

        $this->assertTrue(PlatformActivity::seemsToCoverSoftwareSales());
    }

    public function test_legal_document_seeder_creates_all_eight_documents_pending_review(): void
    {
        $this->seed(LegalDocumentSeeder::class);

        $this->assertSame(8, LegalDocument::count());
        $this->assertSame(0, LegalDocument::where('requires_legal_review', false)->count());
    }

    public function test_a_draft_legal_document_shows_the_pending_review_banner_publicly(): void
    {
        $this->seed(LegalDocumentSeeder::class);

        $response = $this->get(route('legal', 'zatca-disclaimer'));

        $response->assertOk();
        $response->assertSee('has not yet been reviewed by a licensed legal advisor');
    }

    public function test_a_super_admin_can_edit_a_legal_document(): void
    {
        $this->seed(LegalDocumentSeeder::class);
        $document = LegalDocument::where('slug', 'cookie-policy')->firstOrFail();

        $response = $this->actingAs($this->makeSuperAdmin())->put(route('admin.legal-documents.update', $document), [
            'title_en' => 'Cookie Policy',
            'body_en' => '<p>Updated content.</p>',
            'status' => 'published',
            'requires_legal_review' => '0',
        ]);

        $response->assertRedirect(route('admin.legal-documents.index'));
        $document->refresh();
        $this->assertSame('published', $document->status);
        $this->assertFalse($document->requires_legal_review);
        $this->assertStringContainsString('Updated content.', $document->body_en);
    }

    public function test_a_non_super_admin_cannot_edit_legal_documents(): void
    {
        $this->seed(LegalDocumentSeeder::class);
        $document = LegalDocument::where('slug', 'terms')->firstOrFail();
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->get(route('admin.legal-documents.edit', $document));

        $response->assertForbidden();
    }

    public function test_compliance_checklist_shows_a_warning_when_no_activity_covers_software_and_zatca_is_not_verified(): void
    {
        $this->seed(PlatformIdentitySeeder::class);
        $this->seed(LegalDocumentSeeder::class);

        $response = $this->actingAs($this->makeSuperAdmin())->get(route('admin.compliance.index'));

        $response->assertOk();
        $response->assertSee('Not verified');
        $response->assertSee('None of your registered activities');
    }

    public function test_a_super_admin_can_record_verified_zatca_certification_status(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.compliance.zatca-status.update'), [
            'certified' => '1',
            'note' => 'Certificate #12345, issued 2026-01-01',
        ]);

        $response->assertRedirect();
        $this->assertTrue(Setting::getBool('platform_zatca_certified'));
        $this->assertSame('Certificate #12345, issued 2026-01-01', Setting::get('platform_zatca_certification_note'));
    }

    public function test_a_super_admin_can_add_and_remove_a_registered_activity(): void
    {
        $owner = $this->makeSuperAdmin();

        $this->actingAs($owner)->post(route('admin.compliance.activities.store'), [
            'code' => '999999',
            'description_ar' => 'نشاط تجريبي',
        ])->assertRedirect();

        $activity = PlatformActivity::where('code', '999999')->firstOrFail();

        $this->actingAs($owner)->delete(route('admin.compliance.activities.destroy', $activity))->assertRedirect();

        $this->assertDatabaseMissing('platform_activities', ['id' => $activity->id]);
    }

    public function test_a_non_super_admin_cannot_reach_the_compliance_checklist(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->get(route('admin.compliance.index'));

        $response->assertForbidden();
    }
}
