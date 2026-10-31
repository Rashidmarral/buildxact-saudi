<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Add an option to directly fit [the stamp] on the invoice" — a
 * per-company stamp size setting (Settings -> Company stamp) that
 * overrides each PDF layout's own default width. Requires the 'stamps'
 * feature the same way uploading a stamp already does.
 */
class CompanyStampSizeSettingTest extends TestCase
{
    use RefreshDatabase;

    private function requiredFields(): array
    {
        return [
            'name' => 'Stamp Co.',
            'invoice_prefix' => 'INV',
            'primary_customer_type' => 'mixed',
            'negative_number_format' => 'minus',
        ];
    }

    private function makeCompany(bool $withStampsFeature = true): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(), 'price_monthly' => 100, 'price_yearly' => 1000,
            'is_active' => true, 'has_stamps' => $withStampsFeature,
        ]);
        $company = Company::create(['name' => 'Stamp Co.', 'slug' => 'stamp-'.uniqid(), 'status' => 'active']);
        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $company;
    }

    private function makeOwner(Company $company): User
    {
        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_a_company_can_set_a_stamp_size_override(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->put(route('app.settings.update'), $this->requiredFields() + ['stamp_size' => 180]);

        $response->assertSessionDoesntHaveErrors();
        $this->assertSame(180, $company->fresh()->stamp_size);
    }

    public function test_choosing_default_clears_the_override_back_to_null(): void
    {
        $company = $this->makeCompany();
        $company->update(['stamp_size' => 180]);
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->put(route('app.settings.update'), $this->requiredFields() + ['stamp_size' => '']);

        $response->assertSessionDoesntHaveErrors();
        $this->assertNull($company->fresh()->stamp_size);
    }

    public function test_an_out_of_range_stamp_size_is_rejected(): void
    {
        $company = $this->makeCompany();
        $owner = $this->makeOwner($company);

        $response = $this->actingAs($owner)->put(route('app.settings.update'), $this->requiredFields() + ['stamp_size' => 1000]);

        $response->assertSessionHasErrors('stamp_size');
        $this->assertNull($company->fresh()->stamp_size);
    }

    public function test_the_stamp_size_field_is_hidden_without_the_stamps_feature(): void
    {
        $company = $this->makeCompany(withStampsFeature: false);
        $owner = $this->makeOwner($company);

        $this->actingAs($owner)->get(route('app.settings.index'))
            ->assertOk()
            ->assertDontSee(__('Stamp size on PDF'));
    }
}
