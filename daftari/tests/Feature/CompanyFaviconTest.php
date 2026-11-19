<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature request: "add option in setting to upload icon for companies as
 * well" — the platform already had a favicon setting (super admin only);
 * each company can now upload its own, so a team can tell their own
 * account's browser tab apart from other open tabs. Falls back to no
 * custom icon (the app's own default) when a company hasn't set one.
 */
class CompanyFaviconTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(): Company
    {
        $company = Company::create(['name' => 'Favicon Co.', 'slug' => 'favicon-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function baseSettingsPayload(Company $company): array
    {
        return [
            'name' => $company->name,
            'invoice_prefix' => 'INV-',
            'primary_customer_type' => 'mixed',
            'negative_number_format' => 'minus',
        ];
    }

    public function test_uploading_a_favicon_saves_it_on_the_company(): void
    {
        Storage::fake('public');
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->put(route('app.settings.update'), $this->baseSettingsPayload($company) + [
            'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ]);

        $response->assertRedirect();
        $company->refresh();
        $this->assertNotNull($company->favicon_path);
        Storage::disk('public')->assertExists($company->favicon_path);
    }

    public function test_uploading_a_new_favicon_replaces_and_deletes_the_old_one(): void
    {
        Storage::fake('public');
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $this->actingAs($owner)->put(route('app.settings.update'), $this->baseSettingsPayload($company) + [
            'favicon' => UploadedFile::fake()->image('first.png', 32, 32),
        ]);
        $firstPath = $company->refresh()->favicon_path;

        $this->actingAs($owner)->put(route('app.settings.update'), $this->baseSettingsPayload($company) + [
            'favicon' => UploadedFile::fake()->image('second.png', 32, 32),
        ]);

        $company->refresh();
        $this->assertNotSame($firstPath, $company->favicon_path);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($company->favicon_path);
    }

    public function test_the_app_layout_renders_the_companys_favicon_when_set(): void
    {
        Storage::fake('public');
        $company = $this->makeCompany();
        $company->update(['favicon_path' => 'favicons/test-icon.png']);
        Storage::disk('public')->put('favicons/test-icon.png', 'fake-image-content');
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.clients.index'));

        $response->assertOk();
        $response->assertSee('favicons/test-icon.png', false);
    }

    public function test_the_app_layout_has_no_custom_favicon_link_when_the_company_has_not_set_one(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.clients.index'));

        $response->assertOk();
        $response->assertDontSee('favicons/', false);
    }
}
