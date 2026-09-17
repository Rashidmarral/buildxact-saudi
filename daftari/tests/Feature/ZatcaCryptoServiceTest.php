<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Zatca\ZatcaCryptoService;
use Tests\TestCase;

/**
 * signInvoiceHash() must return openssl_sign()'s plain ASN.1 DER signature,
 * base64-encoded, with no conversion to raw r||s. A prior attempt
 * converted it to raw r||s on the theory that the declared SignatureMethod
 * (xmldsig-more's ecdsa-sha256, whose governing RFC 4050 nominally calls
 * for that format) required it — that produced compliance-check
 * rejections that persisted even after fixing other genuine issues, and
 * was reverted after cross-checking against a commercially available,
 * independently-verified-working ZATCA Phase 2 reference implementation
 * (Ultimate POS's ZATCA module), whose equivalent of this method is
 * `openssl_sign(...); base64_encode($sig)` with no r||s conversion either.
 */
class ZatcaCryptoServiceTest extends TestCase
{
    public function test_signed_hash_is_the_plain_der_signature_and_verifies_correctly(): void
    {
        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $privateKeyPem);

        $company = new Company(['zatca_private_key' => $privateKeyPem]);
        $hashBase64 = base64_encode(hash('sha256', 'test invoice content', true));

        $signature = app(ZatcaCryptoService::class)->signInvoiceHash($company, $hashBase64);
        $this->assertNotNull($signature);

        $der = base64_decode($signature);
        // A DER-encoded ECDSA signature is a SEQUENCE of two INTEGERs —
        // starts with 0x30, variable length (typically 70-72 bytes for a
        // 256-bit curve), never the fixed 64-byte raw r||s concatenation.
        $this->assertSame(0x30, ord($der[0]), 'signInvoiceHash() must return openssl_sign()\'s DER output (SEQUENCE tag 0x30), not a converted raw r||s signature.');

        $publicKeyPem = openssl_pkey_get_details($key)['key'];
        $this->assertSame(1, openssl_verify($hashBase64, $der, $publicKeyPem, OPENSSL_ALGO_SHA256), 'The signature does not verify against the signing key for the given message.');
    }
}
