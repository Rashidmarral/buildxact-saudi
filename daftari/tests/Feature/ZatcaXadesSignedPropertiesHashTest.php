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
 * document combination with "Invalid signed properties hashing", even
 * though B2B/standard combinations passed cleanly. Two encoding-only
 * fixes (hex-vs-raw digest output, then the SignedProperties id string)
 * each checked out against a commercially available, independently-
 * verified-working ZATCA Phase 2 reference implementation (Ultimate
 * POS's ZATCA module) but neither changed the rejection, pointing at the
 * actual bytes being hashed rather than their encoding.
 *
 * Root cause: ZatcaXadesSigner::sign() computed the digest input via
 * $signedProperties->C14N() — a true C14N canonicalization, which also
 * pulls in every namespace merely *in scope* on the node (ext:, sig:)
 * regardless of whether SignedProperties actually uses them. The
 * reference implementation never canonicalizes anything for this digest
 * despite declaring the same xml-c14n11 CanonicalizationMethod: it
 * builds the XML with \XMLWriter and hashes \XMLReader::readInnerXml()'s
 * literal text — the exact bytes about to be transmitted, not a
 * canonical form of them. ZATCA's validator apparently does the
 * equivalent of a literal re-hash of the transmitted bytes rather than a
 * true C14N-invariant recomputation.
 *
 * This test drives the real compliance-check route end to end with a
 * genuine EC key pair and self-signed certificate, captures the actual
 * signed XML ZatcaXadesSigner::sign() produced, and independently
 * recomputes the SignedProperties digest by re-parsing that XML and
 * literally re-serializing the embedded node — asserting it matches what
 * was actually placed in the ds:Reference's DigestValue, and that it
 * does NOT match either of the two previously-tried (and wrong) digest
 * inputs: C14N() and a version with the old hyphenated id.
 */
class ZatcaXadesSignedPropertiesHashTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_signed_properties_digest_is_the_literal_serialized_bytes_not_c14n(): void
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

        $expectedDigest = base64_encode(hash('sha256', trim($doc->saveXML($signedProperties)), false));
        $wrongC14nDigest = base64_encode(hash('sha256', $signedProperties->C14N(), false));
        $wrongRawDigest = base64_encode(hash('sha256', $signedProperties->C14N(), true));

        $this->assertNotSame($wrongRawDigest, $declaredDigest, 'The declared digest matches the old raw-then-base64 encoding — a previously-reverted bug has resurfaced.');
        $this->assertNotSame($wrongC14nDigest, $declaredDigest, 'The declared digest is hashed from a true C14N canonicalization rather than the literal transmitted bytes — the fix did not take effect.');
        $this->assertSame($expectedDigest, $declaredDigest, 'ZatcaXadesSigner::sign() must hash the literal serialized bytes of the SignedProperties node (DOMDocument::saveXML($node)), not a C14N-canonicalized form of it.');
    }
}
