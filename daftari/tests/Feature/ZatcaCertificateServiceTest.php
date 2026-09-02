<?php

namespace Tests\Feature;

use App\Services\Zatca\ZatcaCertificateService;
use Tests\TestCase;

/**
 * Bug report: the compliance check failed every simplified/B2C document
 * type with "ECDSA Public Key does not match with qr code ECDSA public
 * key" (publicKey_QRCODE_INVALID) while the identical standard/B2B types
 * passed cleanly. Root cause: publicKeyBytes() (QR tag 8) returned the
 * full DER-encoded SubjectPublicKeyInfo (algorithm identifier + curve OID
 * ahead of the point), not the bare 65-byte uncompressed EC point ZATCA's
 * validator expects and independently re-derives from the X.509
 * certificate to compare tag 8 against. Standard/B2B invoices apparently
 * aren't checked this strictly at compliance-check time (they go through
 * interactive clearance afterward), which is why the bug only surfaced
 * for simplified/B2C.
 */
class ZatcaCertificateServiceTest extends TestCase
{
    public function test_public_key_bytes_returns_the_bare_uncompressed_point_not_the_full_subject_public_key_info(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);
        $this->assertNotFalse($key, 'Unable to generate a test EC key pair — is the openssl extension missing secp256k1 support?');

        $details = openssl_pkey_get_details($key);
        $expectedPoint = "\x04".$details['ec']['x'].$details['ec']['y'];
        $this->assertSame(65, strlen($expectedPoint), 'Test fixture sanity check: an uncompressed secp256k1 point is 65 bytes.');

        $dn = ['commonName' => 'Test EGS', 'organizationName' => 'Test Co', 'countryName' => 'SA'];
        $csr = openssl_csr_new($dn, $key, ['digest_alg' => 'sha256']);
        $cert = openssl_csr_sign($csr, null, $key, 365, ['digest_alg' => 'sha256']);
        openssl_x509_export($cert, $certPem);

        $certBase64 = base64_encode(str_replace(["-----BEGIN CERTIFICATE-----", "-----END CERTIFICATE-----", "\r", "\n"], '', $certPem));

        $point = app(ZatcaCertificateService::class)->publicKeyBytes($certBase64);

        $this->assertSame(65, strlen($point), 'publicKeyBytes() must return the bare 65-byte uncompressed point, not the full SubjectPublicKeyInfo DER blob.');
        $this->assertSame("\x04", $point[0], 'The point must start with the uncompressed-point marker 0x04.');
        $this->assertSame($expectedPoint, $point, "publicKeyBytes() didn't return the certificate's actual EC point.");
    }
}
