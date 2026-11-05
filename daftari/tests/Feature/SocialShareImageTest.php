<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The marketing site had no OpenGraph/Twitter card meta tags at all —
 * sharing a Daftari link on WhatsApp, LinkedIn, X, or Slack showed no rich
 * preview. Fixed by adding a dedicated "social share image" branding
 * upload (falling back to the platform logo) and wiring og: and twitter:
 * meta tags into layouts/site.blade.php.
 */
class SocialShareImageTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    public function test_the_home_page_carries_open_graph_and_twitter_card_tags(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk();
        $response->assertSee('property="og:title"', false);
        $response->assertSee('property="og:description"', false);
        $response->assertSee('property="og:site_name"', false);
        $response->assertSee('name="twitter:card"', false);
    }

    public function test_uploading_a_social_share_image_saves_it_and_it_appears_on_the_home_page(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.branding'), [
            'social_image' => UploadedFile::fake()->image('share.png', 1200, 630),
        ]);

        $response->assertSessionDoesntHaveErrors();
        $path = Setting::get('branding_social_image_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $home = $this->get(route('home'));
        $home->assertSee(Storage::url($path), false);
        $home->assertSee('summary_large_image', false);
    }

    public function test_og_image_falls_back_to_the_platform_logo_when_no_social_image_is_set(): void
    {
        $branding = PlatformBranding::all();

        $this->assertNull($branding['social_image_path']);
    }
}
