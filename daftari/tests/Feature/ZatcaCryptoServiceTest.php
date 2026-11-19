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

    /**
     * genesisHash() used to hash an empty string and base64-encode the
     * raw 32-byte digest (base64_encode(hash('sha256', '', true))) —
     * ZATCA's own documented base-case instead hashes the single
     * character "0" and base64-encodes the 64-character *hex* digest,
     * not the raw bytes. Nothing locally validates a company's chosen
     * genesis value against ZATCA's own, so the old, wrong value would
     * have passed every local check and only ever surfaced as a
     * rejected first-ever invoice submission at ZATCA's end. Pins the
     * exact well-known constant so a regression back to either mistake
     * fails immediately, locally, instead of silently in production.
     */
    public function test_genesis_hash_matches_zatcas_documented_base_case_not_an_empty_string_hash(): void
    {
        $genesisHash = app(ZatcaCryptoService::class)->genesisHash();

        $this->assertSame('NWZlY2ViNjZmZmM4NmYzOGQ5NTI3ODZjNmQ2OTZjNzljMmRiYzIzOWRkNGU5MWI0NjcyOWQ3M2EyN2ZiNTdlOQ==', $genesisHash);
        $this->assertSame('5feceb66ffc86f38d952786c6d696c79c2dbc239dd4e91b46729d73a27fb57e9', base64_decode($genesisHash), 'must be the base64 of the hex digest string, not the raw binary digest');
        $this->assertNotSame(base64_encode(hash('sha256', '', true)), $genesisHash, 'must hash "0", not an empty string');
    }
}
