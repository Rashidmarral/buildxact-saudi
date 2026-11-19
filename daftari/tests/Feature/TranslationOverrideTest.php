<?php

namespace Tests\Feature;

use App\Models\Translation;
use App\Models\User;
use App\Support\DbOverlayTranslationLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Website CMS follow-up: admin-editable translation overrides. Proves the
 * DbOverlayTranslationLoader actually wins over the shipped lang/ar.json
 * value (and falls back cleanly when no override exists), and that the
 * admin.translations.* routes are super_admin-only like the rest of the
 * platform-wide (not company-scoped) settings screens.
 */
class TranslationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    protected function tearDown(): void
    {
        DbOverlayTranslationLoader::forget('en', '*');
        DbOverlayTranslationLoader::forget('ar', '*');

        parent::tearDown();
    }

    public function test_a_db_override_wins_over_the_shipped_translation(): void
    {
        app()->setLocale('en');
        $this->assertSame('Log out', __('Log out'));

        Translation::upsert('en', '*', 'Log out', 'Sign off');
        DbOverlayTranslationLoader::forget('en', '*');

        // Simulates the next HTTP request: production picks this up
        // automatically since every request builds a fresh Translator, but
        // within one test method the container would otherwise keep
        // serving the copy it already loaded and cached in memory above.
        app()->forgetInstance('translator');
        app()->forgetInstance('translation.loader');

        $this->assertSame('Sign off', __('Log out'));
    }

    public function test_translations_fall_back_to_the_shipped_value_with_no_override(): void
    {
        app()->setLocale('ar');

        $this->assertNotSame('Log out', __('Log out'));
    }

    public function test_a_super_admin_can_save_a_translation_override(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.translations.update'), [
            'key' => 'Log out',
            'en_value' => 'Sign off',
            'ar_value' => '',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('translations', [
            'locale' => 'en',
            'key' => 'Log out',
            'value' => 'Sign off',
        ]);
    }

    public function test_saving_a_blank_value_clears_an_existing_override(): void
    {
        Translation::upsert('en', '*', 'Log out', 'Sign off');
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)->post(route('admin.translations.update'), [
            'key' => 'Log out',
            'en_value' => '',
            'ar_value' => '',
        ]);

        $this->assertDatabaseMissing('translations', ['locale' => 'en', 'key' => 'Log out']);
    }

    public function test_admin_staff_cannot_reach_the_languages_screen(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $response = $this->actingAs($staff)->get(route('admin.translations.index'));

        $response->assertForbidden();
    }
}
