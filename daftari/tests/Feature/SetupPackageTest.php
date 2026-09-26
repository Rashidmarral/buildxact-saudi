<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SetupPackage;
use App\Models\SetupPackageRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Item 9 of the Sales, Compliance & Business Growth request: "Done-For-You
 * Setup Package — configurable paid packages". No package or price is
 * seeded — the operator defines every package in Admin -> Setup
 * Packages. Requesting one feeds the existing Lead CRM (source =
 * setup_package) rather than a parallel pipeline.
 */
class SetupPackageTest extends TestCase
{
    use RefreshDatabase;

    private function makeSuperAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makePackage(array $overrides = []): SetupPackage
    {
        return SetupPackage::create(array_merge([
            'name_en' => 'ZATCA Onboarding Setup',
            'slug' => 'zatca-onboarding-setup-'.uniqid(),
            'price' => 500,
            'is_active' => true,
        ], $overrides));
    }

    public function test_no_setup_package_exists_until_an_admin_creates_one(): void
    {
        $this->assertSame(0, SetupPackage::count());
    }

    public function test_a_super_admin_can_create_a_setup_package_with_a_price_and_features(): void
    {
        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.setup-packages.store'), [
            'name_en' => 'Full Accounting Setup',
            'price' => '1200.00',
            'features_en' => "Chart of accounts configured\nOpening balances entered\nTeam training session",
            'is_active' => '1',
        ]);

        $response->assertRedirect(route('admin.setup-packages.index'));
        $package = SetupPackage::where('name_en', 'Full Accounting Setup')->firstOrFail();
        $this->assertSame(1200.0, (float) $package->price);
        $this->assertCount(3, $package->features_en);
    }

    public function test_a_package_with_no_price_shows_contact_us_for_pricing(): void
    {
        $package = $this->makePackage(['price' => null]);

        $this->assertSame('Contact us for pricing', $package->priceLabel());
    }

    public function test_the_public_page_only_lists_active_packages(): void
    {
        $this->makePackage(['name_en' => 'Active Package', 'is_active' => true]);
        $this->makePackage(['name_en' => 'Inactive Package', 'is_active' => false]);

        $response = $this->get(route('setup-packages.index'));

        $response->assertOk();
        $response->assertSee('Active Package');
        $response->assertDontSee('Inactive Package');
    }

    public function test_requesting_a_package_creates_a_lead_and_a_tracked_request(): void
    {
        $package = $this->makePackage();

        $response = $this->post(route('setup-packages.submit'), [
            'name' => 'Sara Al-Harbi',
            'email' => 'sara@example.test',
            'setup_package_id' => $package->id,
            'message' => 'Need this done before month end.',
        ]);

        $response->assertRedirect();
        $lead = Lead::where('email', 'sara@example.test')->firstOrFail();
        $this->assertSame('setup_package', $lead->source);
        $this->assertStringContainsString($package->name_en, $lead->message);

        $this->assertDatabaseHas('setup_package_requests', [
            'setup_package_id' => $package->id,
            'lead_id' => $lead->id,
            'status' => 'requested',
        ]);
    }

    public function test_requesting_an_inactive_or_unknown_package_id_fails_validation(): void
    {
        $response = $this->post(route('setup-packages.submit'), [
            'name' => 'Test', 'email' => 'test@example.test', 'setup_package_id' => 999999,
        ]);

        $response->assertSessionHasErrors('setup_package_id');
    }

    public function test_a_non_super_admin_cannot_manage_setup_packages(): void
    {
        $staff = User::factory()->create(['role' => 'admin_staff', 'company_id' => null]);

        $this->actingAs($staff)->get(route('admin.setup-packages.index'))->assertForbidden();
    }

    public function test_a_package_with_existing_requests_cannot_be_deleted(): void
    {
        $package = $this->makePackage();
        $lead = Lead::create(['name' => 'X', 'email' => 'x@example.test', 'status' => 'lead', 'source' => 'setup_package']);
        SetupPackageRequest::create(['setup_package_id' => $package->id, 'lead_id' => $lead->id, 'status' => 'requested']);

        $response = $this->actingAs($this->makeSuperAdmin())->delete(route('admin.setup-packages.destroy', $package));

        $response->assertSessionHasErrors('setup_package');
        $this->assertDatabaseHas('setup_packages', ['id' => $package->id]);
    }

    public function test_an_admin_can_update_a_setup_package_requests_status_from_the_lead_page(): void
    {
        $package = $this->makePackage();
        $lead = Lead::create(['name' => 'Y', 'email' => 'y@example.test', 'status' => 'lead', 'source' => 'setup_package']);
        $requestRow = SetupPackageRequest::create(['setup_package_id' => $package->id, 'lead_id' => $lead->id, 'status' => 'requested']);

        $response = $this->actingAs($this->makeSuperAdmin())->post(route('admin.leads.setup-package.update', $lead), [
            'status' => 'completed',
        ]);

        $response->assertRedirect();
        $requestRow->refresh();
        $this->assertSame('completed', $requestRow->status);
        $this->assertNotNull($requestRow->completed_at);
    }

    public function test_the_leads_index_can_filter_by_setup_package_source(): void
    {
        Lead::create(['name' => 'Package Lead', 'email' => 'pkg@example.test', 'status' => 'lead', 'source' => 'setup_package']);
        Lead::create(['name' => 'Other Lead', 'email' => 'other@example.test', 'status' => 'lead', 'source' => 'other']);

        $response = $this->actingAs($this->makeSuperAdmin())->get(route('admin.leads.index', ['source' => 'setup_package']));

        $response->assertOk();
        $response->assertSee('Package Lead');
        $response->assertDontSee('other@example.test');
    }
}
