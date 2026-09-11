<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\PlatformFormat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * Feature request: "add same system setting for companies as well as we
 * have made for superadmin." Date format, time format, and fiscal year
 * start were platform-wide Settings applied identically to every tenant
 * — a company had no way to differ from whatever the superadmin last
 * chose. Companies already had timezone/locale/currency columns for
 * exactly this kind of per-tenant override; date_format, time_format,
 * and fiscal_year_start now join them, checked first by
 * PlatformFormat/ResolvesReportPeriod before falling back to the
 * platform Setting (so an install with no company overrides behaves
 * exactly as before this feature existed).
 */
class RegionalPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function makeCompany(array $overrides = []): Company
    {
        $company = Company::create(array_merge([
            'name' => 'Prefs Co.', 'slug' => 'prefs-'.uniqid(),
        ], $overrides));

        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        Role::seedSystemRoles($company->id);

        return $company;
    }

    public function test_platform_format_uses_the_platform_default_when_the_company_has_no_override(): void
    {
        Setting::set('general_date_format', 'Y-m-d');
        Setting::set('general_time_format', '12h');

        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Auth::login($owner);

        $this->assertSame('2026-01-15', PlatformFormat::date('2026-01-15'));
        $this->assertSame('02:30 PM', PlatformFormat::time('2026-01-15 14:30:00'));
    }

    public function test_platform_format_prefers_the_companys_own_override(): void
    {
        Setting::set('general_date_format', 'Y-m-d');
        Setting::set('general_time_format', '12h');

        $company = $this->makeCompany(['date_format' => 'd/m/Y', 'time_format' => '24h']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        Auth::login($owner);

        $this->assertSame('15/01/2026', PlatformFormat::date('2026-01-15'));
        $this->assertSame('14:30', PlatformFormat::time('2026-01-15 14:30:00'));
    }

    public function test_platform_format_falls_back_to_the_platform_default_with_no_authenticated_user(): void
    {
        Setting::set('general_date_format', 'Y-m-d');

        $this->assertSame('2026-01-15', PlatformFormat::date('2026-01-15'));
    }

    public function test_the_settings_page_saves_and_can_clear_regional_preference_overrides(): void
    {
        $company = $this->makeCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->put(route('app.settings.update'), [
            'name' => $company->name,
            'invoice_prefix' => 'INV-',
            'primary_customer_type' => 'mixed',
            'negative_number_format' => 'minus',
            'date_format' => 'Y-m-d',
            'time_format' => '12h',
            'fiscal_year_start' => 4,
        ]);

        $response->assertRedirect();
        $company->refresh();
        $this->assertSame('Y-m-d', $company->date_format);
        $this->assertSame('12h', $company->time_format);
        $this->assertSame(4, $company->fiscal_year_start);

        // Selecting "Platform default" submits an empty string, which
        // must clear the override back to null (fall through to the
        // platform Setting again), not save the literal empty string.
        $response = $this->actingAs($owner)->put(route('app.settings.update'), [
            'name' => $company->name,
            'invoice_prefix' => 'INV-',
            'primary_customer_type' => 'mixed',
            'negative_number_format' => 'minus',
            'date_format' => '',
            'time_format' => '',
            'fiscal_year_start' => '',
        ]);

        $response->assertRedirect();
        $company->refresh();
        $this->assertNull($company->date_format);
        $this->assertNull($company->time_format);
        $this->assertNull($company->fiscal_year_start);
    }

    public function test_a_companys_own_fiscal_year_start_overrides_the_platform_default_in_reports(): void
    {
        Setting::set('general_fiscal_year_start', 1);

        // Platform default is January, so "this_year" would normally run
        // Jan 1 - Dec 31. Overriding to July means the fiscal year
        // containing "now" starts the most recent July 1st instead.
        $company = $this->makeCompany(['vat_number' => '300012345600003', 'fiscal_year_start' => 7]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $response = $this->actingAs($owner)->get(route('app.audit.index'));
        $response->assertOk();

        $expectedStart = now()->month >= 7
            ? now()->copy()->startOfMonth()->month(7)
            : now()->copy()->startOfMonth()->month(7)->subYear();

        $response->assertSee($expectedStart->format('Y-m-d'));
    }
}
