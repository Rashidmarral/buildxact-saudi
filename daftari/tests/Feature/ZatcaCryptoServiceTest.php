<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Services\Zatca\ZatcaCryptoService;
use Tests\TestCase;

/**
 * Bug report: the compliance check kept failing every simplified/B2C
 * document type with "ECDSA Public Key does not match with qr code ECDSA
 * public key" even after the public key extraction itself was fixed and
 * independently confirmed correct (the private key and certificate were
 * proven to be a genuine matching pair). Root cause: signInvoiceHash()
 * used openssl_sign()'s output directly — PHP always returns EC
 * signatures as ASN.1 DER (SEQUENCE of two INTEGERs), the standard PKI/
 * CMS convention. But the signature block declares SignatureMethod
 * "http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256", and per RFC
 * 4050 (which that URI is defined against), ds:SignatureValue for that
 * algorithm must be the raw r||s concatenation instead — two
 * fixed-length big-endian integers, no DER wrapper. Sending DER bytes
 * there produces a signature ZATCA's validator can't verify against the
 * (otherwise entirely correct) certificate's public key.
 */
class ZatcaCryptoServiceTest extends TestCase
{
    public function test_signed_hash_is_the_raw_64_byte_r_s_concatenation_not_der_and_verifies_correctly(): void
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

        $raw = base64_decode($signature);
        $this->assertSame(64, strlen($raw), 'A raw ECDSA signature for secp256k1 must be exactly 64 bytes (32-byte r + 32-byte s), not variable-length DER.');

        // Round-trip: rebuild a DER signature from the raw r||s halves and
        // confirm it's still cryptographically valid for the same message
        // and key — proving the conversion preserved r and s correctly
        // rather than corrupting them.
        $r = substr($raw, 0, 32);
        $s = substr($raw, 32, 32);
        $der = $this->encodeDerSignature($r, $s);

        $publicKeyPem = openssl_pkey_get_details($key)['key'];
        $this->assertSame(1, openssl_verify($hashBase64, $der, $publicKeyPem, OPENSSL_ALGO_SHA256), 'The raw r||s signature did not round-trip to a valid signature — r or s was corrupted during conversion.');
    }

    private function encodeDerSignature(string $r, string $s): string
    {
        $encodeInteger = function (string $bytes): string {
            $bytes = ltrim($bytes, "\x00");
            if ($bytes === '' || (ord($bytes[0]) & 0x80) !== 0) {
                $bytes = "\x00".$bytes;
            }

            return "\x02".chr(strlen($bytes)).$bytes;
        };

        $body = $encodeInteger($r).$encodeInteger($s);

        return "\x30".chr(strlen($body)).$body;
    }
}
