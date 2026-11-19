<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Zatca\ZatcaSyncService;
use App\Services\ZatcaQrGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Confirmed against a real ZATCA Simulation clearance (standard/B2B
 * invoice, Zubaidi company test case): for a standard invoice, ZATCA
 * re-signs the cleared document with its OWN certificate rather than
 * echoing back ours — a different X509 cert (self-signed
 * "zatca-einvoicing-sim" vs. our CSID, issued by ZATCA's
 * PEZEINVOICESCA2-CA), a different signature and public key, and a QR
 * with only 8 TLV tags instead of our 9 (the certificate-signature tag
 * is dropped). Before this fix, submit() always kept the QR it rendered
 * locally BEFORE calling ZATCA, even after a successful clearance handed
 * back a materially different, now-authoritative document. For a
 * standard/B2B invoice this must prefer the QR embedded in ZATCA's own
 * clearedInvoice once clearance succeeds.
 *
 * The embedded TLV payload below is taken verbatim from that real
 * clearance's clearedInvoice (base64-decoded), so this also guards
 * against any regression in how the QR's AdditionalDocumentReference
 * node is located and decoded.
 */
class ZatcaClearedInvoiceQrExtractionTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_CSID = 'MIIDBzCCAe+gAwIBAgIUBSx0rLzK3YZPX+xqWHd5Snjpe5AwDQYJKoZIhvcNAQELBQAwEzERMA8GA1UEAwwIdGVzdC1lZ3MwHhcNMjYwODMwMjAzODAyWhcNMzYwODI3MjAzODAyWjATMREwDwYDVQQDDAh0ZXN0LWVnczCCASIwDQYJKoZIhvcNAQEBBQADggEPADCCAQoCggEBALq2jcY9XpSEhbetKvAAcMAP7Hjp5uJk7eo8luKn5Rgl9QqM/Bwgjuz6xKKASmn6QSaZOk44wdafGJvi/5MQ9fDVO1bCEUWDFVbMDblBxEjBe3N9FsQ33u4x1uZAUndQMaBukxH3+XxW7bGGCfkYJwJaDbSA6HAPF8kOzFNVjQKmyf3vOHa3uajxwMG4XKXqifFFhmn4jgCIhD5Nd6tLvY0dLMjD+MG7EVLPJCf0BGIMbyRJR6KWbz+lcCrO8hAC2UPX9jObTcz/kQQSDXWS8XnKjxyCr+BTVWZNYfIGOz3Y8YMM36IHBsHG/mIT3GXX6KKK4T9MmoGXcV87pV0dQusCAwEAAaNTMFEwHQYDVR0OBBYEFMVsaLNnewpMwUC33hHUv1RhoyBPMB8GA1UdIwQYMBaAFMVsaLNnewpMwUC33hHUv1RhoyBPMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggEBAKJPnEQOZlEPhevrJxthiJ2qZnemUKvvrdCJ1e5TqWG3+H2q+35dKjPE3QbPCnJtuw9iL54nkby8DiGrHRowJ5BcoxJbFernKLljBxCxRHOAp7M//nDXrYfWwrdDUqd4GE/T0buNrrCLSLEWdMxS1vEh4j/CV8h9wh9EVS7jgo99487iY/PxolzU5+Wjb+bxsgkrySpKhZt3En4A0jq3+3bP5bdFkj1fhmoAYzcIzUZj9ldcwrqesHGkF+SpGxRh6fll6pHZUnP+zqJH3Jqy1Ccer+M/MVmuqKQtwnMSb4yfRn8u9ffmzAAsBXnCSaceZYaYRPh/dubVhFrie20ODXY=';

    /** Taken verbatim from a real ZATCA Simulation clearedInvoice's QR. */
    private const CLEARED_QR_TLV_BASE64 = 'AVjZhdik2LPYs9ipINi52KjYr9in2YTZhdis2YrYryDYudio2K/Yp9mE2YTZhyDYqNmGINi52KjZitivINin2YTYstio2YrYr9mKINmE2YTYtdmK2KfZhtipAg8zMTA0NjQ1NjA2MDAwMDMDEzIwMjYtMTEtMDNUMTM6NDQ6MjIEBDIuMzAFBDAuMzAGLHEyMit1OCtKckZ6bU9aenFyWm5LWWoyQjBmNlN6a1FSNnNJMDBTVjBUMUU9B2BNRVVDSUhXMklkZHFMU1FqeEVUQXRmSmR2clAwMVNqdTdKMmtYbDExUTJndExtMXpBaUVBd3dsZVN2NDYva0pnbGdLRUkwaUFYNHFuUU5QZkxLTFhTOFZ6QWd2MGk3ND0IWzBZMBMGByqGSM49AgEGCCqGSM49AwEHA0IABHJ7W+qe1j3RJem8fmGkn5BVxucFjcNyiAMmpH+Jh+dtlFxMuBkU3rioicn3QrvC62b/DJplM4ZdT8NIsQmeQKM=';

    private function clearedInvoiceXmlBase64(): string
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2"
         xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
         xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2">
    <cac:AdditionalDocumentReference>
        <cbc:ID>QR</cbc:ID>
        <cac:Attachment>
            <cbc:EmbeddedDocumentBinaryObject mimeCode="text/plain">{$this->tlv()}</cbc:EmbeddedDocumentBinaryObject>
        </cac:Attachment>
    </cac:AdditionalDocumentReference>
</Invoice>
XML;

        return base64_encode($xml);
    }

    private function tlv(): string
    {
        return self::CLEARED_QR_TLV_BASE64;
    }

    private function makeOnboardedCompany(): Company
    {
        $plan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-'.uniqid(),
            'price_monthly' => 100, 'price_yearly' => 1000, 'is_active' => true,
            'has_zatca_phase2' => true,
        ]);

        // A real EC key + self-signed cert — buildSignedPayload() needs an
        // actual signable key/certificate pair to produce a pre-submission
        // QR at all. Mirrors ZatcaQrOnlyPersistedOnConfirmedSuccessTest's
        // fixture; this pre-submission QR is deliberately NOT the one
        // this test expects to see persisted.
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'secp256k1']);
        openssl_pkey_export($key, $privateKeyPem);
        $csr = openssl_csr_new(['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'], $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);
        $csid = base64_encode(str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\r", "\n"], '', $certPem));

        $company = Company::create([
            'name' => 'Cleared QR Co.', 'slug' => 'cleared-qr-'.uniqid(),
            'currency' => 'SAR',
            'zatca_integration_mode' => Company::ZATCA_MODE_PHASE2,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_private_key' => $privateKeyPem,
            'zatca_production_csid' => $csid, 'zatca_production_secret' => 'secret',
        ]);

        Subscription::create(['company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active', 'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth()]);

        return $company;
    }

    private function makeStandardInvoice(Company $company): Invoice
    {
        $client = Client::create(['company_id' => $company->id, 'name' => 'Client', 'vat_number' => '300012345600003']);

        return Invoice::create([
            'company_id' => $company->id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'status' => 'sent', 'issue_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 100, 'discount_total' => 0, 'vat_total' => 15, 'total' => 115,
        ]);
    }

    public function test_a_cleared_b2b_invoice_gets_the_qr_from_zatcas_cleared_invoice_not_the_pre_submission_one(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => $this->clearedInvoiceXmlBase64()], 200)]);
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeStandardInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('cleared', $log->status);

        $expectedQr = ZatcaQrGenerator::renderFromBase64Tlv($this->tlv());
        $this->assertSame($expectedQr, $invoice->fresh()->qr_code, 'A cleared standard/B2B invoice must carry the QR embedded in ZATCA\'s own re-signed clearedInvoice, not the one rendered before submission.');

        $expectedXml = base64_decode($this->clearedInvoiceXmlBase64());
        $this->assertSame($expectedXml, $log->fresh()->xml_payload, 'The downloadable XML must also be ZATCA\'s own re-signed clearedInvoice, so it always agrees with the QR now shown on the invoice.');
    }

    public function test_a_malformed_cleared_invoice_falls_back_to_the_pre_submission_qr_and_xml(): void
    {
        Http::fake(['*' => Http::response(['clearedInvoice' => 'not-valid-base64-xml!!'], 200)]);
        $company = $this->makeOnboardedCompany();
        $invoice = $this->makeStandardInvoice($company);

        $log = app(ZatcaSyncService::class)->submit($invoice);

        $this->assertSame('cleared', $log->status);
        $this->assertNotNull($invoice->fresh()->qr_code, 'A malformed/unparseable clearedInvoice must not leave the invoice with no QR at all — fall back to the pre-submission one.');
        $this->assertStringContainsString('<Invoice', $log->fresh()->xml_payload, 'A malformed/unparseable clearedInvoice must not wipe out the downloadable XML — fall back to the pre-submission one.');
    }
}
