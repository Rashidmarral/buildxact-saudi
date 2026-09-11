<?php

namespace Tests\Feature;

use App\Services\Zatca\ZatcaCertificateService;
use Tests\TestCase;

/**
 * publicKeyBytes() (QR tag 8) must return the certificate's public key as
 * the full DER-encoded SubjectPublicKeyInfo — algorithm identifier + the
 * 0x04||X||Y point — not the bare point alone. A prior attempt to strip
 * this down to the bare 65-byte point (reasoning that ZATCA's validator
 * re-derives the key from the X.509 certificate and compares raw points)
 * produced compliance-check rejections that persisted even after fixing
 * other genuine issues, and was reverted after cross-checking against a
 * commercially available, independently-verified-working ZATCA Phase 2
 * reference implementation (Ultimate POS's ZATCA module) whose equivalent
 * of this method also embeds the full DER form as-is.
 */
class ZatcaCertificateServiceTest extends TestCase
{
    public function test_public_key_bytes_returns_the_full_der_subject_public_key_info(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);
        $this->assertNotFalse($key, 'Unable to generate a test EC key pair — is the openssl extension missing secp256k1 support?');

        $dn = ['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);

        $certBase64 = base64_encode(str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"], '', $certPem));

        $publicKeyPem = openssl_pkey_get_details($key)['key'];
        $expectedDer = base64_decode(str_replace(["-----BEGIN PUBLIC KEY-----", "-----END PUBLIC KEY-----", "\r", "\n"], '', $publicKeyPem));

        $result = app(ZatcaCertificateService::class)->publicKeyBytes($certBase64);

        $this->assertSame($expectedDer, $result, "publicKeyBytes() must return the certificate's full SubjectPublicKeyInfo DER, matching its PEM-decoded public key exactly.");
        $this->assertGreaterThan(65, strlen($result), 'The full DER SubjectPublicKeyInfo is longer than the bare 65-byte point — the algorithm identifier must still be present.');
    }
}
