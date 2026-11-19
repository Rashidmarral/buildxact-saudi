<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "check what we enhance in it" (ZATCA Phase 2 deep audit) — a company's
 * production/compliance certificate has a real ZATCA-set expiry, and
 * nothing anywhere in the app previously surfaced it: a company could go
 * from every submission succeeding to every submission being rejected
 * overnight, with only a generic ZATCA error to go on and no earlier
 * warning. The dashboard now parses the stored certificate itself (no
 * new column — derived on render from ZatcaCertificateService::validTo())
 * and shows a banner once it's within 30 days of expiry, or already past
 * it.
 */
class ZatcaCertificateExpiryWarningTest extends TestCase
{
    use RefreshDatabase;

    private function generateCertificate(int $validDays): string
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
        $csr = openssl_csr_new(['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, $validDays, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);

        return base64_encode(str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $certPem));
    }

    private function makeOwner(string $productionCsid): array
    {
        $company = Company::create([
            'name' => 'Cert Expiry Co.', 'slug' => 'cert-expiry-'.uniqid(),
            'vat_number' => '399999999900003', 'cr_number' => 'CRN999999',
            'street_name' => 'Test Street', 'city' => 'Riyadh',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_environment' => 'simulation',
            'zatca_sync_b2b' => true, 'zatca_sync_b2c' => true,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_csr' => 'SIM-CSR', 'zatca_private_key' => 'sim-private-key',
            'zatca_compliance_request_id' => 'sim-compliance-req',
            'zatca_compliance_csid' => $productionCsid, 'zatca_compliance_secret' => 'sim-compliance-secret',
            'zatca_production_request_id' => 'sim-production-req',
            'zatca_production_csid' => $productionCsid, 'zatca_production_secret' => 'sim-production-secret',
            'zatca_linked_at' => now(),
        ]);

        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_an_expired_certificate_shows_a_red_banner(): void
    {
        [, $owner] = $this->makeOwner($this->generateCertificate(-5));

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        $response->assertSee(__('Your :cert ZATCA certificate has expired', ['cert' => __('production')]));
    }

    public function test_a_certificate_expiring_soon_shows_an_amber_warning(): void
    {
        [, $owner] = $this->makeOwner($this->generateCertificate(10));

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        $response->assertSee('10');
    }

    public function test_a_certificate_valid_for_a_long_time_shows_no_banner(): void
    {
        [, $owner] = $this->makeOwner($this->generateCertificate(365));

        $response = $this->actingAs($owner)->get(route('app.zatca.dashboard'));

        $response->assertOk();
        $response->assertDontSee(__('has expired'));
        $response->assertDontSee('expires in', false);
    }
}
