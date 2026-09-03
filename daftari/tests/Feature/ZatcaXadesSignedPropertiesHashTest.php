<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Bug report: the ZATCA compliance check rejected every simplified/B2C
 * document combination with "Invalid signed properties hashing" (and a
 * cascading "publicKey_QRCODE_INVALID"), even though B2B/standard
 * combinations passed cleanly. Root cause: ZatcaXadesSigner::sign()
 * computed the xades:SignedProperties digest as
 * base64_encode(hash('sha256', $signedProperties->C14N(), true)) — raw
 * digest bytes, directly base64-encoded — but ZATCA's validator expects
 * the same "hex string, then base64" double-encoding already used for the
 * certificate digest (ZatcaCertificateService::certificateHash()):
 * base64_encode(hash('sha256', ..., false)). Confirmed against a
 * commercially available, independently-verified-working ZATCA Phase 2
 * reference implementation (Ultimate POS's ZATCA module), whose
 * GetSignedPropertiesHashEncoded() uses the hex-then-base64 form while its
 * plain invoice content hash uses the raw form — an inconsistent-looking
 * but apparently ZATCA-mandated convention.
 *
 * This test drives the real compliance-check route end to end with a
 * genuine EC key pair and self-signed certificate, captures the actual
 * signed XML ZatcaXadesSigner::sign() produced, and independently
 * recomputes the SignedProperties digest from the embedded node —
 * asserting it matches what was actually placed in the ds:Reference's
 * DigestValue rather than trusting the signer's own computation.
 */
class ZatcaXadesSignedPropertiesHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_signed_properties_digest_is_hex_then_base64_not_raw_then_base64(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);
        $this->assertNotFalse($key, 'Unable to generate a test EC key pair — is the openssl extension missing secp256k1 support?');
        openssl_pkey_export($key, $privateKeyPem);

        $dn = ['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);

        // ZATCA's real binarySecurityToken is base64-encoded twice — the
        // PEM body itself is already base64(DER), so one more
        // base64_encode() here gives the same double-encoded form
        // ZatcaCertificateService::singlyEncoded() detects and unwraps.
        $certBase64 = base64_encode(str_replace(
            ["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"],
            '',
            $certPem
        ));

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
            'zatca_csr' => 'fake-csr',
            'zatca_private_key' => $privateKeyPem,
            'zatca_compliance_request_id' => 'req-123',
            'zatca_compliance_csid' => $certBase64,
            'zatca_compliance_secret' => 'test-secret',
        ]);

        Subscription::create([
            'company_id' => $company->id, 'plan_id' => $plan->id, 'status' => 'active',
            'billing_cycle' => 'monthly', 'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        $capturedXml = null;
        Http::fake(function ($request) use (&$capturedXml) {
            if ($capturedXml === null && str_contains($request->url(), '/compliance/invoices')) {
                $capturedXml = base64_decode($request->data()['invoice']);
            }

            return Http::response(['validationResults' => ['status' => 'PASS']], 200);
        });

        $this->actingAs($owner)->post(route('app.zatca.compliance-check'));

        $this->assertNotNull($capturedXml, 'The compliance endpoint was never called — the signer likely threw before submission.');

        $doc = new DOMDocument;
        $doc->loadXML($capturedXml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        $signedPropertiesNodes = $xpath->query("//xades:SignedProperties[@Id='xadesSignedProperties']");
        $this->assertSame(1, $signedPropertiesNodes->length, 'Expected exactly one xades:SignedProperties node with Id="xadesSignedProperties".');
        $signedProperties = $signedPropertiesNodes->item(0);

        $digestValueNodes = $xpath->query("//ds:Reference[@URI='#xadesSignedProperties']/ds:DigestValue");
        $this->assertSame(1, $digestValueNodes->length, 'Expected exactly one ds:Reference pointing at the SignedProperties with a DigestValue.');
        $declaredDigest = $digestValueNodes->item(0)->textContent;

        $expectedDigest = base64_encode(hash('sha256', $signedProperties->C14N(), false));
        $wrongLegacyDigest = base64_encode(hash('sha256', $signedProperties->C14N(), true));

        $this->assertNotSame($wrongLegacyDigest, $declaredDigest, 'The declared digest matches the old raw-then-base64 encoding — the fix did not take effect.');
        $this->assertSame($expectedDigest, $declaredDigest, 'ZatcaXadesSigner::sign() must hex-encode the SignedProperties SHA-256 digest before base64-encoding it, matching certificateHash()\'s convention.');
    }
}
