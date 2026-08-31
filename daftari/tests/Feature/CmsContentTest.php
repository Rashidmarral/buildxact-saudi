<?php

namespace Tests\Feature;

use App\Models\CmsSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website CMS: the admin-managed content blocks behind the public marketing
 * pages (Admin\CmsController + CmsSection::forPage()). Covers the two things
 * most likely to silently regress — the item-sync-by-delete-then-recreate
 * on update, and a saved change actually reaching the public page — plus
 * that only a super admin (not a granular admin_staff) can touch it, same
 * as Platform Settings.
 */
class CmsContentTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_a_super_admin_can_add_a_section_to_a_page(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.cms.sections.store', 'home'), [
            'type' => 'faq',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('cms_sections', ['page' => 'home', 'type' => 'faq']);
    }

    public function test_updating_a_section_replaces_its_items_rather_than_appending(): void
    {
        $section = CmsSection::create(['page' => 'home', 'type' => 'faq', 'sort_order' => 1]);
        $section->items()->create(['sort_order' => 1, 'title_en' => 'Old question', 'body_en' => 'Old answer']);
        $section->items()->create(['sort_order' => 2, 'title_en' => 'Old question 2', 'body_en' => 'Old answer 2']);

        $response = $this->actingAs($this->makeSuperAdmin())->put(route('admin.cms.sections.update', $section), [
            'title_en' => 'FAQ',
            'items' => [
                ['title_en' => 'New question', 'body_en' => 'New answer'],
            ],
        ]);

        $response->assertRedirect();
        $section->refresh();
        $this->assertCount(1, $section->items);
        $this->assertSame('New question', $section->items->first()->title_en);
    }

    public function test_a_saved_change_appears_on_the_public_page(): void
    {
        $section = CmsSection::create(['page' => 'home', 'type' => 'hero', 'sort_order' => 1, 'title_en' => 'Original title']);

        $this->actingAs($this->makeSuperAdmin())->put(route('admin.cms.sections.update', $section), [
            'is_active' => '1',
            'title_en' => 'Freshly edited hero title',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('Freshly edited hero title');
    }

    public function test_deactivating_a_section_hides_it_from_the_public_page(): void
    {
        $section = CmsSection::create(['page' => 'home', 'type' => 'hero', 'sort_order' => 1, 'title_en' => 'Should disappear']);

        $this->actingAs($this->makeSuperAdmin())->put(route('admin.cms.sections.update', $section), [
            // is_active omitted — an unchecked checkbox sends nothing.
            'title_en' => 'Should disappear',
        ]);

        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertDontSee('Should disappear');
    }

    public function test_deleting_a_section_removes_its_items_too(): void
    {
        $section = CmsSection::create(['page' => 'home', 'type' => 'faq', 'sort_order' => 1]);
        $item = $section->items()->create(['sort_order' => 1, 'title_en' => 'Q', 'body_en' => 'A']);

        $this->actingAs($this->makeSuperAdmin())->delete(route('admin.cms.sections.destroy', $section));

        $this->assertDatabaseMissing('cms_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('cms_section_items', ['id' => $item->id]);
    }

    public function test_admin_staff_cannot_reach_the_website_cms(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->get(route('admin.cms.pages.show', 'home'));

        $response->assertForbidden();
    }
}
