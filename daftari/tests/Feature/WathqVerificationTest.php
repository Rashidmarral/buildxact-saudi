<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "cr verification have both option manual and also get auto pull also" —
 * manual CR entry on the Client/Supplier forms already worked (plain
 * fillable fields); this is the new "Verify via Wathq" auto-pull on top of
 * it. One platform-level Wathq API key (PlatformSettingsController
 * ::updateIntegrations()) powers every company's lookups — see
 * WathqService for why it isn't a per-company credential like SMS/WhatsApp.
 */
class WathqVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'company_id' => null]);
    }

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_saving_the_wathq_api_key_requires_a_freshly_confirmed_password(): void
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.integrations.update'), [
            'wathq_api_key' => 'secret-key',
        ]);

        $response->assertRedirect(route('admin.password.confirm'));
        $this->assertFalse(Setting::isConfigured('wathq_api_key'));
    }

    public function test_an_admin_can_save_the_wathq_api_key_and_it_is_encrypted_at_rest(): void
    {
        $admin = $this->makeAdmin();
        session(['auth.password_confirmed_at' => time()]);

        $response = $this->actingAs($admin)->post(route('admin.settings.integrations.update'), [
            'wathq_api_key' => 'super-secret-wathq-key',
        ]);

        $response->assertRedirect();
        $this->assertSame('super-secret-wathq-key', Setting::get('wathq_api_key'));

        $rawColumn = \Illuminate\Support\Facades\DB::table('settings')->where('key', 'wathq_api_key')->value('value');
        $this->assertNotSame('super-secret-wathq-key', $rawColumn);
    }

    public function test_leaving_the_wathq_api_key_blank_keeps_the_existing_key(): void
    {
        $admin = $this->makeAdmin();
        session(['auth.password_confirmed_at' => time()]);
        Setting::set('wathq_api_key', 'original-key', encrypted: true);

        $this->actingAs($admin)->post(route('admin.settings.integrations.update'), [
            'wathq_api_key' => '',
        ]);

        $this->assertSame('original-key', Setting::get('wathq_api_key'));
    }

    public function test_cr_lookup_requires_a_cr_number(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.wathq.commercial-registration'), []);

        $response->assertSessionHasErrors('cr_number');
    }

    public function test_cr_lookup_fails_gracefully_when_wathq_is_not_configured(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.commercial-registration'), [
            'cr_number' => '1010101010',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_cr_lookup_fails_gracefully_when_wathq_cannot_be_reached(): void
    {
        Setting::set('wathq_api_key', 'a-real-key', encrypted: true);
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timed out'));
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.commercial-registration'), [
            'cr_number' => '1010101010',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_cr_lookup_returns_mapped_company_details_on_success(): void
    {
        Setting::set('wathq_api_key', 'a-real-key', encrypted: true);
        Http::fake([
            'api.wathq.sa/*' => Http::response([
                'crNumber' => '1010101010',
                'crName' => 'Dynamic Core Contracting Co.',
                'crNameAr' => 'شركة دايناميك كور للمقاولات',
                'status' => 'Active',
            ], 200),
        ]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.commercial-registration'), [
            'cr_number' => '1010101010',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'cr_number' => '1010101010',
                'name' => 'Dynamic Core Contracting Co.',
                'name_ar' => 'شركة دايناميك كور للمقاولات',
                'status' => 'Active',
            ],
        ]);
        Http::assertSent(fn ($request) => $request->hasHeader('apiKey', 'a-real-key'));
    }

    public function test_cr_lookup_reports_a_clean_error_when_the_cr_number_is_not_found(): void
    {
        Setting::set('wathq_api_key', 'a-real-key', encrypted: true);
        Http::fake(['api.wathq.sa/*' => Http::response(null, 404)]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.commercial-registration'), [
            'cr_number' => '9999999999',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_national_address_lookup_requires_a_short_address(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->post(route('app.wathq.national-address'), []);

        $response->assertSessionHasErrors('short_address');
    }

    public function test_national_address_lookup_fails_gracefully_when_wathq_is_not_configured(): void
    {
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.national-address'), [
            'short_address' => 'RRRD2929',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }

    public function test_national_address_lookup_returns_mapped_address_on_success(): void
    {
        Setting::set('wathq_api_key', 'a-real-key', encrypted: true);
        Http::fake([
            'api.wathq.sa/*' => Http::response([
                'shortAddress' => 'RRRD2929',
                'buildingNumber' => '7452',
                'streetName' => 'King Fahd Road',
                'district' => 'Al Olaya',
                'city' => 'Riyadh',
                'postCode' => '12333',
                'additionalNumber' => '4321',
            ], 200),
        ]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.national-address'), [
            'short_address' => 'RRRD2929',
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'data' => [
                'short_address' => 'RRRD2929',
                'building_number' => '7452',
                'street_name' => 'King Fahd Road',
                'district' => 'Al Olaya',
                'city' => 'Riyadh',
                'postal_code' => '12333',
                'additional_number' => '4321',
            ],
        ]);
    }

    public function test_national_address_lookup_reports_a_clean_error_when_not_found(): void
    {
        Setting::set('wathq_api_key', 'a-real-key', encrypted: true);
        Http::fake(['api.wathq.sa/*' => Http::response(null, 404)]);
        $owner = $this->makeOwner();

        $response = $this->actingAs($owner)->postJson(route('app.wathq.national-address'), [
            'short_address' => 'ZZZZ0000',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['success' => false]);
    }
}
