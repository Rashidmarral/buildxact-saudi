<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug report: the compliance check failed every one of the 6 required
 * document combinations with ZATCA's BR-KSA-04 ("issue date must be <=
 * current date"), even though the server's own clock was independently
 * confirmed accurate to the second. Root cause: App\Http\Middleware\
 * SetTimezone switches PHP's default timezone to the logged-in company's
 * own timezone for the rest of the request (so displayed dates render in
 * local time) — but ZatcaController::runComplianceCheck() computed its
 * shared instant with a bare now(), which after that middleware runs
 * returns the company's *local* wall-clock time while the XML and QR
 * code both treat it as UTC. For a company 3 hours ahead of UTC (e.g.
 * Asia/Riyadh), that mislabeling occasionally pushed the submitted date
 * into "tomorrow" relative to ZATCA's real UTC clock.
 *
 * Fixed by requesting now('UTC') explicitly instead of relying on
 * whatever the ambient default timezone happens to be.
 */
class ZatcaComplianceCheckTimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_compliance_sample_timestamp_is_genuine_utc_even_when_the_companys_timezone_is_ahead_of_it(): void
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $company = Company::create([
            'name' => 'Riyadh Co.', 'slug' => 'riyadh-co-'.uniqid(),
            'vat_number' => '399999999900003', 'cr_number' => 'CRN999999',
            'street_name' => 'Test Street', 'city' => 'Riyadh',
            'timezone' => 'Asia/Riyadh',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_environment' => 'simulation',
            'zatca_sync_b2b' => true,
            'zatca_sync_b2c' => false,
            'zatca_onboarding_status' => 'compliance_pending',
            'zatca_csr' => 'fake-csr', 'zatca_private_key' => 'fake-private-key',
            'zatca_compliance_request_id' => 'req-123',
            'zatca_compliance_csid' => 'fake-compliance-csid',
            'zatca_compliance_secret' => 'fake-compliance-secret',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $capturedXml = null;
        Http::fake(function ($request) use (&$capturedXml) {
            if (str_contains($request->url(), '/compliance/invoices')) {
                $capturedXml = base64_decode($request->data()['invoice']);
            }

            return Http::response(['validationResults' => ['status' => 'PASS']], 200);
        });

        $beforeUtc = now('UTC');
        $this->actingAs($owner)->post(route('app.zatca.compliance-check'));
        $afterUtc = now('UTC');

        $this->assertNotNull($capturedXml, 'The compliance endpoint was never called.');

        preg_match('/<cbc:IssueDate>(.+?)<\/cbc:IssueDate>/', $capturedXml, $dateMatch);
        preg_match('/<cbc:IssueTime>(.+?)<\/cbc:IssueTime>/', $capturedXml, $timeMatch);

        $submitted = \Carbon\Carbon::parse($dateMatch[1].' '.$timeMatch[1], 'UTC');

        // If SetTimezone's Asia/Riyadh override had leaked into $issuedAt,
        // this would be off by ~3 hours (in the future) — well outside
        // any reasonable request-execution window.
        $this->assertTrue(
            $submitted->betweenIncluded($beforeUtc->subMinute(), $afterUtc->addMinute()),
            "Submitted timestamp {$submitted} was not within a minute of real UTC ({$beforeUtc} - {$afterUtc}) — the company's local timezone likely leaked into the ZATCA payload."
        );
    }
}
