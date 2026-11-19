<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Audit finding ZATCA-04: the QR code (and the signature/cryptographic
 * stamp it's built from) used to be saved onto the invoice BEFORE the
 * outbound clearance/reporting call was even made — so a submission
 * ZATCA went on to reject still left the invoice carrying a QR that
 * encodes a locally-signed, never-actually-cleared document. The QR is
 * only meaningful once ZATCA has genuinely confirmed this exact
 * submission, so it must only be persisted alongside a confirmed
 * cleared/reported log entry.
 */
class ZatcaQrOnlyPersistedOnConfirmedSuccessTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    private function makeOnboardedCompany(bool $withSigningKey = false): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        $csid = self::TEST_CSID;
        $privateKeyPem = null;

        if ($withSigningKey) {
            // A real EC key + self-signed cert — buildSignedPayload() needs
            // an actual signable key/certificate pair to produce a QR at
            // all, which the fixed TEST_CSID constant alone can't provide.
            // Mirrors ZatcaQrTimestampMatchesIssueTimeTest's fixture.
            $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
            openssl_pkey_export($key, $privateKeyPem);
            $csr = openssl_csr_new(['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'], $key, ['digest_alg' => 'sha256']);
            $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
            openssl_x509_export($cert, $certPem);
            $csid = base64_encode(str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $certPem));
        }

        $company = Company::create([
            'name' => 'QR Co.', 'slug' => 'qr-'.uniqid(),
            'currency' => 'SAR',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_private_key' => $privateKeyPem,
            'zatca_production_csid' => $csid, 'zatca_production_secret' => 'secret',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $company;
    }

    private function makeInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300012345600003']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_a_rejected_submission_leaves_the_invoice_with_no_qr_code(): void
    {
        Http::fake(['*' => Http::response(['validationResults' => ['status' => 'ERROR']], 400)]);
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertNull($invoice->fresh()->qr_code, 'A rejected submission must not leave a QR on the invoice — it would encode a document ZATCA never actually cleared.');
    }

    public function test_a_connection_failure_leaves_the_invoice_with_no_qr_code(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('simulated timeout');
        });
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('failed', $log->status);
        $this->assertNull($invoice->fresh()->qr_code);
    }

    public function test_a_confirmed_cleared_submission_does_save_the_qr_code(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'stamp'], 200)]);
        $company = $this->makeOnboardedCompany(withSigningKey: true);
        $invoice = $this->makeInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('cleared', $log->status);
        $this->assertNotNull($invoice->fresh()->qr_code);
    }
}
