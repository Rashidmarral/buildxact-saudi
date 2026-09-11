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
 * document combination with "ECDSA Public Key does not match with qr code
 * ECDSA public key", while standard/B2B combinations passed cleanly (ZATCA
 * does not cryptographically re-validate the embedded QR for standard
 * invoices — only for simplified ones — which is why this bug never
 * surfaced there even though the same QR is embedded in both).
 *
 * Root cause: ZatcaSyncService::buildSignedPayload() passed
 * base64_encode($publicKey) into ZatcaQrGenerator's tag-8 slot, and the QR
 * generator wrote that value straight into the TLV as the tag's *value* —
 * putting the ASCII characters of a base64 string into the QR's copy of
 * the public key instead of the actual key bytes. Tags 6 and 7 (invoice
 * hash, signature) are correctly base64 *text* per ZATCA's own convention,
 * but tag 8 (and tag 9) must be the raw bytes, exactly as tag 9
 * (certificate signature) already was.
 *
 * Confirmed against a commercially available, independently-verified-
 * working ZATCA Phase 2 reference implementation (Ultimate POS's ZATCA
 * module): its Cert509XParser::getCertificatePublicKeyEncoded() —
 * misleadingly named — returns base64_decode($publicKeyPem), i.e. raw
 * bytes, and feeds that raw value directly into its own QR TLV builder.
 *
 * This test drives the real compliance-check route end to end with a
 * genuine EC key pair and self-signed certificate, captures the actual
 * signed XML produced, extracts and TLV-parses the embedded QR code, and
 * asserts tag 8's raw value is the certificate's actual DER public key
 * bytes — not a base64-text rendering of them.
 */
class ZatcaQrPublicKeyRawBytesTest extends TestCase
{
    use RefreshDatabase;

    public function test_qr_tag_8_is_the_raw_public_key_bytes_not_base64_text(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);
        $this->assertNotFalse($key, 'Unable to generate a test EC key pair — is the openssl extension missing secp256k1 support?');
        openssl_pkey_export($key, $privateKeyPem);

        $publicKeyPem = openssl_pkey_get_details($key)['key'];
        $expectedPublicKeyRaw = base64_decode(str_replace(
            ["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\r", "\n"],
            '',
            $publicKeyPem
        ));

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
        $xpath->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $xpath->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $qrNodes = $xpath->query("//cac:AdditionalDocumentReference[cbc:ID='QR']//cbc:EmbeddedDocumentBinaryObject");
        $this->assertSame(1, $qrNodes->length, 'Expected exactly one QR AdditionalDocumentReference with an embedded binary object.');

        $tlv = base64_decode($qrNodes->item(0)->textContent);
        $this->assertNotFalse($tlv, 'The QR payload did not decode as valid base64.');

        $tags = [];
        $offset = 0;
        $len = strlen($tlv);
        while ($offset < $len) {
            $tag = ord($tlv[$offset]);
            $valueLength = ord($tlv[$offset + 1]);
            $tags[$tag] = substr($tlv, $offset + 2, $valueLength);
            $offset += 2 + $valueLength;
        }

        $this->assertSame($offset, $len, 'The QR TLV did not parse cleanly to the end of the payload — a tag length is wrong.');
        $this->assertArrayHasKey(8, $tags, 'QR tag 8 (public key) is missing.');

        $wrongLegacyValue = base64_encode($expectedPublicKeyRaw);

        $this->assertNotSame($wrongLegacyValue, $tags[8], 'QR tag 8 holds the base64 *text* of the public key rather than its raw bytes — the fix did not take effect.');
        $this->assertSame($expectedPublicKeyRaw, $tags[8], 'QR tag 8 must be the raw DER SubjectPublicKeyInfo bytes, matching the certificate\'s actual public key.');
    }
}
