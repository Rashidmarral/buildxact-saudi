<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Zatca\ZatcaXadesSigner;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Bug report: the ZATCA compliance check rejected every simplified/B2C
 * document combination with "Invalid signed properties hashing", even
 * though B2B/standard combinations passed cleanly. A run of narrower
 * fixes here (hex-vs-raw digest output, the SignedProperties id string,
 * true C14N vs. compact-literal DOM serialization, a redundant
 * xmlns:xades declaration) each independently checked out against a
 * commercially available, independently-verified-working ZATCA Phase 2
 * reference implementation (Ultimate POS's ZATCA module) but none
 * changed the rejection — because none of them touched the one thing
 * that implementation actually does differently: it never uses
 * DOMDocument for this digest at all. It builds the *entire* invoice via
 * \XMLWriter with 4-space indentation, then hashes
 * \XMLReader::readInnerXml()'s literal extracted text for just the
 * xades:SignedProperties element — which faithfully preserves that
 * element's real indentation (9 levels deep in the full document tree:
 * Invoice > ext:UBLExtensions > ext:UBLExtension > ext:ExtensionContent
 * > sig:UBLDocumentSignatures > sac:SignatureInformation > ds:Signature
 * > ds:Object > xades:QualifyingProperties).
 *
 * This was confirmed conclusively — not just plausible — against a real
 * invoice the user downloaded directly from their working Ultimate POS
 * ZATCA installation's own synced records (genuinely accepted by ZATCA,
 * not merely self-consistent with our own code): hashing that invoice's
 * own xades:SignedProperties fragment exactly as it appears (hex digest,
 * then base64) reproduces its own declared ds:DigestValue byte for byte.
 * ZatcaXadesSigner::buildPrettyPrintedSignedProperties() reproduces that
 * exact literal text via \XMLWriter directly (no DOMDocument involved),
 * using 9 placeholder wrapper elements purely to reach the correct
 * indentation depth before writing the real element (\XMLWriter's
 * setIndent() derives indentation from actual call nesting; there's no
 * API to set a starting indent level directly).
 */
class ZatcaXadesSignedPropertiesHashTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The gold-standard regression test: feeds buildPrettyPrintedSignedProperties()
     * the exact real-world values from a genuine ZATCA-accepted invoice
     * (downloaded by the user from their own working Ultimate POS ZATCA
     * installation) and asserts both byte-identical reproduction of that
     * invoice's actual xades:SignedProperties element and that hashing it
     * (hex digest, then base64) reproduces ZATCA's own declared
     * DigestValue for that exact invoice — not just self-consistency with
     * our own code.
     */
    public function test_pretty_printed_signed_properties_matches_a_real_zatca_accepted_invoice(): void
    {
        $signingTimestamp = '2026-08-03T07:07:39Z';
        $certificateHash = 'YzllMmVhNmY3Zjc3YzZlMmIxMGQ4MTUyMWYzM2E4YjgyNzIxNjhlMzk3OTEzNGY4NjM5ZWRiODEzZDMzM2JmMg==';
        $issuerName = 'CN=eInvoicing';
        $serialNumber = '1785713317955';
        $zatcaDeclaredDigest = 'ZDMxMzgxMDU5NDFhYjJhMmIyZGVhMGJiYjlhZTc1OGQzOTFiMTNmY2EzMjEzMGU3NTQ3M2Q3NWE5ZTlmMWJhNw==';

        // The exact literal xades:SignedProperties fragment as it appears
        // in the real, ZATCA-accepted invoice — including its real
        // 9-levels-deep indentation — copied verbatim from the file the
        // user downloaded from their own Ultimate POS ZATCA installation.
        $realFragment = <<<'XML'
<xades:SignedProperties xmlns:xades="http://uri.etsi.org/01903/v1.3.2#" Id="xadesSignedProperties">
                                        <xades:SignedSignatureProperties>
                                            <xades:SigningTime>2026-08-03T07:07:39Z</xades:SigningTime>
                                            <xades:SigningCertificate>
                                                <xades:Cert>
                                                    <xades:CertDigest>
                                                        <ds:DigestMethod xmlns:ds="http://www.w3.org/2000/09/xmldsig#" Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"> </ds:DigestMethod>
                                                        <ds:DigestValue xmlns:ds="http://www.w3.org/2000/09/xmldsig#">YzllMmVhNmY3Zjc3YzZlMmIxMGQ4MTUyMWYzM2E4YjgyNzIxNjhlMzk3OTEzNGY4NjM5ZWRiODEzZDMzM2JmMg==</ds:DigestValue>
                                                    </xades:CertDigest>
                                                    <xades:IssuerSerial>
                                                        <ds:X509IssuerName xmlns:ds="http://www.w3.org/2000/09/xmldsig#">CN=eInvoicing</ds:X509IssuerName>
                                                        <ds:X509SerialNumber xmlns:ds="http://www.w3.org/2000/09/xmldsig#">1785713317955</ds:X509SerialNumber>
                                                    </xades:IssuerSerial>
                                                </xades:Cert>
                                            </xades:SigningCertificate>
                                        </xades:SignedSignatureProperties>
                                    </xades:SignedProperties>
XML;

        // Sanity check on the fixture itself, independent of any of our
        // code: hashing the real invoice's own fragment reproduces its
        // own declared digest. If this ever fails, the fixture itself
        // was transcribed wrong — not our implementation.
        $this->assertSame(
            $zatcaDeclaredDigest,
            base64_encode(hash('sha256', $realFragment, false)),
            'Fixture sanity check failed: hashing the real ZATCA-accepted invoice\'s own SignedProperties fragment no longer reproduces its own declared digest — the fixture was transcribed incorrectly.'
        );

        $method = new ReflectionMethod(ZatcaXadesSigner::class, 'buildPrettyPrintedSignedProperties');
        $method->setAccessible(true);
        $ours = $method->invoke(
            app(ZatcaXadesSigner::class),
            $signingTimestamp, $certificateHash, $issuerName, $serialNumber,
        );

        $this->assertSame(
            $realFragment,
            $ours,
            'ZatcaXadesSigner::buildPrettyPrintedSignedProperties() must byte-for-byte reproduce the real ZATCA-accepted invoice\'s xades:SignedProperties element for the same inputs.'
        );
        $this->assertSame(
            $zatcaDeclaredDigest,
            base64_encode(hash('sha256', $ours, false)),
            'The digest computed from our reproduction must match the DigestValue ZATCA actually accepted for the real invoice this fixture was taken from.'
        );
    }

    /**
     * Drives the real compliance-check route end to end and confirms the
     * signer actually wires buildPrettyPrintedSignedProperties() into the
     * submitted document — both as the digest input and (spliced in place
     * of the compact DOM-serialized form) the literal transmitted bytes —
     * rather than the gold-standard test above passing in isolation while
     * production code takes a different path.
     */
    public function test_the_compliance_route_actually_uses_the_pretty_printed_digest(): void
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

        // The transmitted bytes must carry the pretty-printed (9-levels
        // indented) form, not DOM's compact serialization — spot-checked
        // via the distinctive indentation on SignedSignatureProperties
        // (10 levels: 40 spaces) rather than the compact
        // "><xades:SignedSignatureProperties>" DOM would produce.
        $this->assertStringContainsString(
            "\n".str_repeat(' ', 40).'<xades:SignedSignatureProperties>',
            $capturedXml,
            'The transmitted XML must contain the pretty-printed (4-space-per-level) SignedProperties subtree, not a compact DOM-serialized one.'
        );

        $doc = new DOMDocument;
        $doc->loadXML($capturedXml);
        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');
        $xpath->registerNamespace('xades', 'http://uri.etsi.org/01903/v1.3.2#');

        $signingTime = $xpath->query('//xades:SigningTime')->item(0)?->textContent;
        $certDigest = $xpath->query('//xades:CertDigest/ds:DigestValue')->item(0)?->textContent;
        $issuerName = $xpath->query('//xades:IssuerSerial/ds:X509IssuerName')->item(0)?->textContent;
        $serialNumber = $xpath->query('//xades:IssuerSerial/ds:X509SerialNumber')->item(0)?->textContent;
        $this->assertNotNull($signingTime);
        $this->assertNotNull($certDigest);
        $this->assertNotNull($issuerName);
        $this->assertNotNull($serialNumber);

        $declaredDigest = $xpath->query("//ds:Reference[@URI='#xadesSignedProperties']/ds:DigestValue")->item(0)?->textContent;
        $this->assertNotNull($declaredDigest);

        $method = new ReflectionMethod(ZatcaXadesSigner::class, 'buildPrettyPrintedSignedProperties');
        $method->setAccessible(true);
        $expectedFragment = $method->invoke(app(ZatcaXadesSigner::class), $signingTime, $certDigest, $issuerName, $serialNumber);
        $expectedDigest = base64_encode(hash('sha256', $expectedFragment, false));

        $this->assertSame(
            $expectedDigest,
            $declaredDigest,
            'The declared SignedProperties digest must be the hex-then-base64 hash of buildPrettyPrintedSignedProperties()\'s output for this invoice\'s actual values.'
        );
        $this->assertStringContainsString(
            $expectedFragment,
            $capturedXml,
            'The pretty-printed SignedProperties fragment used for the digest must be the exact bytes actually embedded in the transmitted document.'
        );
    }
}
