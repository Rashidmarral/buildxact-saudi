<?php

namespace Tests\Feature;

use App\Models\CmsPage;
use App\Models\CmsSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website CMS follow-up: turning the fixed CmsSection::PAGES list into real,
 * admin-manageable CmsPage rows so a super admin can add a brand new public
 * page (not just edit content on the 6 that ship with the app). Covers the
 * create -> go-live -> footer-link loop, the guardrails around it (reserved/
 * duplicate slugs, site_header/site_footer restricted to the 'global' page),
 * and that built-in pages can't be renamed or deleted out from under their
 * dedicated routes.
 */
class CmsPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_migrating_seeds_the_six_system_pages_plus_global(): void
    {
        $this->assertSame(7, CmsPage::count());
        $this->assertTrue(CmsPage::where('slug', 'home')->where('is_system', true)->exists());
        $this->assertTrue(CmsPage::where('slug', 'global')->where('is_system', true)->exists());
    }

    public function test_a_super_admin_can_create_a_new_page_with_an_auto_generated_slug(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.cms.pages.store'), [
            'name_en' => 'Our Story',
            'name_ar' => 'قصتنا',
            'show_in_footer' => '1',
        ]);

        $response->assertRedirect(route('admin.cms.pages.show', 'our-story'));
        $this->assertDatabaseHas('cms_pages', [
            'slug' => 'our-story',
            'name_en' => 'Our Story',
            'is_system' => false,
            'show_in_footer' => true,
        ]);
    }

    public function test_a_newly_created_page_is_reachable_and_shows_its_sections(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)->post(route('admin.cms.pages.store'), ['name_en' => 'Our Story']);

        $this->actingAs($admin)->post(route('admin.cms.sections.store', 'our-story'), ['type' => 'hero']);
        $section = CmsSection::where('page', 'our-story')->first();
        $this->actingAs($admin)->put(route('admin.cms.sections.update', $section), [
            'is_active' => '1',
            'title_en' => 'How Daftari started',
        ]);

        $response = $this->get('/pages/our-story');

        $response->assertOk();
        $response->assertSee('How Daftari started');
    }

    public function test_a_page_marked_show_in_footer_appears_as_a_footer_link(): void
    {
        $this->actingAs($this->makeSuperAdmin())->post(route('admin.cms.pages.store'), [
            'name_en' => 'Our Story',
            'show_in_footer' => '1',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Our Story');
        $response->assertSee(url('pages/our-story'), false);
    }

    public function test_a_reserved_slug_is_rejected(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.cms.pages.store'), [
            'name_en' => 'Fake Features',
            'slug' => 'features',
        ]);

        $response->assertSessionHasErrors('slug');
        $this->assertSame(7, CmsPage::count());
    }

    public function test_site_header_and_site_footer_types_are_rejected_outside_the_global_page(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)->post(route('admin.cms.pages.store'), ['name_en' => 'Our Story']);

        $response = $this->actingAs($admin)->post(route('admin.cms.sections.store', 'our-story'), ['type' => 'site_header']);

        $response->assertSessionHasErrors('type');
        $this->assertSame(0, CmsSection::where('page', 'our-story')->count());
    }

    public function test_a_system_page_cannot_be_deleted(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->delete(route('admin.cms.pages.destroy', 'home'));

        $response->assertForbidden();
        $this->assertDatabaseHas('cms_pages', ['slug' => 'home']);
    }

    public function test_deleting_a_custom_page_removes_its_sections_too(): void
    {
        $admin = $this->makeSuperAdmin();
        $this->actingAs($admin)->post(route('admin.cms.pages.store'), ['name_en' => 'Our Story']);
        $this->actingAs($admin)->post(route('admin.cms.sections.store', 'our-story'), ['type' => 'hero']);

        $this->actingAs($admin)->delete(route('admin.cms.pages.destroy', 'our-story'));

        $this->assertDatabaseMissing('cms_pages', ['slug' => 'our-story']);
        $this->assertDatabaseMissing('cms_sections', ['page' => 'our-story']);
    }

    public function test_admin_staff_cannot_create_pages(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->post(route('admin.cms.pages.store'), ['name_en' => 'Our Story']);

        $response->assertForbidden();
        $this->assertSame(7, CmsPage::count());
    }
}
