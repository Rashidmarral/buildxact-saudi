<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The settings page was rebuilt from one long stacked page into tabs
 * (Company / Documents / Security / Integrations / Business Rules),
 * mirroring the pattern already used on the admin platform settings page.
 * This just guards that the restructure didn't drop a section or break
 * rendering for a real company.
 */
class SettingsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_page_renders_with_all_tabs_for_an_owner(): void
    {
        $company = Company::create(['name' => 'Settings Co.', 'slug' => 'settings-'.uniqid()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.settings.index'));

        $response->assertOk();
        $response->assertSee('Company', false);
        $response->assertSee('Documents');
        $response->assertSee('Security');
        $response->assertSee('Integrations');
        $response->assertSee('Business Rules');
        $response->assertSee('Change password');
        $response->assertSee('Two-factor authentication');
        $response->assertSee('Webhooks');
        $response->assertSee('Tax rates');
    }
}
