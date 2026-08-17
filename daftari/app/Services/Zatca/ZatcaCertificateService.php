<?php

namespace App\Services\Zatca;

use phpseclib3\File\X509;
use RuntimeException;

/**
 * Parses the X.509 certificate ZATCA issues at CSID time (returned as
 * `binarySecurityToken`, base64-encoded DER) for the fields the invoice's
 * XAdES digital signature block needs: the certificate's own raw bytes,
 * its SHA-256 digest, its issuer DN, its serial number, and — for QR tag
 * 9 — the CA's own signature over the certificate (proof ZATCA itself
 * issued this EGS its signing identity, not a per-invoice value).
 */
class ZatcaCertificateService
{
    /**
     * ZATCA's binarySecurityToken (the value we store as the compliance/
     * production CSID) is, in practice, base64-encoded *twice*: decoding
     * it once yields another base64 string — the "real" base64(DER) form
     * every other method here works with — not the raw DER bytes
     * themselves. This detects which form we were actually given (by
     * checking whether the once-decoded value parses as a certificate)
     * rather than assuming, so a CSID that ever arrives single-encoded
     * still works instead of throwing.
     */
    private function singlyEncoded(string $certificateBase64): string
    {
        $certificateBase64 = trim($certificateBase64);
        $candidate = base64_decode($certificateBase64, true);

        if ($candidate !== false && (new X509)->loadX509($this->wrap($candidate)) !== false) {
            return $candidate;
        }

        return $certificateBase64;
    }

    private function wrap(string $body): string
    {
        return "-----BEGIN CERTIFICATE-----\r\n".trim($body)."\r\n-----END CERTIFICATE-----";
    }

    private function load(string $certificateBase64): array
    {
        $x509 = new X509;
        $parsed = $x509->loadX509($this->wrap($this->singlyEncoded($certificateBase64)));

        if ($parsed === false) {
            throw new RuntimeException('Unable to parse the ZATCA-issued certificate.');
        }

        return [$x509, $parsed];
    }

    /**
     * Raw DER certificate bytes — embedded verbatim (re-encoded to
     * base64) as ds:X509Certificate in the invoice's UBLExtensions.
     */
    public function rawCertificate(string $certificateBase64): string
    {
        return base64_decode($this->singlyEncoded($certificateBase64));
    }

    /**
     * SHA-256 digest of the certificate, matching the reference
     * implementation's convention: it hashes the singly-encoded
     * base64(DER) *string* (not the raw DER bytes), and the hex digest
     * string is itself base64-encoded (not the raw digest bytes) — this
     * is what ZATCA's validator expects for CertDigest/DigestValue here
     * specifically, distinct from the plain base64(raw-bytes) convention
     * used for the main invoice hash.
     */
    public function certificateHash(string $certificateBase64): string
    {
        return base64_encode(hash('sha256', $this->singlyEncoded($certificateBase64), false));
    }

    /**
     * The certificate issuer's Distinguished Name as an RFC 2253-style
     * string (most specific RDN first — e.g. "CN=...,DC=...,DC=..."),
     * needed for xades:IssuerSerial/X509IssuerName.
     */
    public function issuerName(string $certificateBase64): string
    {
        [$x509] = $this->load($certificateBase64);

        $issuerInfo = $x509->getIssuerDN(X509::DN_OPENSSL);
        $names = [];

        foreach ($issuerInfo as $oid => $value) {
            if ($oid === '0.9.2342.19200300.100.1.25') {
                foreach ((array) $value as $component) {
                    $names[] = 'DC='.$component;
                }
            } elseif ($oid === 'CN') {
                $names[] = 'CN='.$value;
            } elseif (in_array($oid, ['O', 'OU', 'C', 'L', 'ST'], true)) {
                $names[] = $oid.'='.$value;
            }
        }

        return implode(', ', array_reverse($names));
    }

    /**
     * The certificate's serial number as a plain decimal string, for
     * xades:IssuerSerial/X509SerialNumber.
     */
    public function serialNumber(string $certificateBase64): string
    {
        [, $parsed] = $this->load($certificateBase64);

        return $parsed['tbsCertificate']['serialNumber']->toString();
    }

    /**
     * ZATCA's own CA signature over this certificate — QR tag 9. This is
     * a property of the certificate itself (proof ZATCA issued it to this
     * EGS), not of any individual invoice, so it's the same value for
     * every invoice signed under this CSID/production certificate.
     */
    public function certificateSignature(string $certificateBase64): string
    {
        [, $parsed] = $this->load($certificateBase64);

        $signature = $parsed['signature'];

        // phpseclib returns the signature as a bitstring; the leading
        // byte is the "unused bits" count for DER BIT STRINGs, which for
        // an octet-aligned ECDSA signature is always 0 and must be
        // stripped before use.
        $hex = unpack('H*', $signature)[1];

        return pack('H*', substr($hex, 2));
    }

    /**
     * The certificate's public key, raw uncompressed SEC1 point bytes
     * (0x04||X||Y) — QR tag 8. Reading it from the certificate itself
     * (rather than re-deriving from our stored private key) is the more
     * robust source of truth, though the two should always agree since
     * ZATCA issues the certificate for the exact public key in our CSR.
     */
    public function publicKeyBytes(string $certificateBase64): string
    {
        [$x509] = $this->load($certificateBase64);

        $publicKeyPem = (string) $x509->getPublicKey();
        $key = openssl_pkey_get_public($publicKeyPem);

        if ($key === false) {
            throw new RuntimeException('Unable to read the public key from the ZATCA-issued certificate.');
        }

        $details = openssl_pkey_get_details($key);

        if (empty($details['ec']['x']) || empty($details['ec']['y'])) {
            throw new RuntimeException('Certificate public key is not an EC key.');
        }

        return "\x04".$details['ec']['x'].$details['ec']['y'];
    }
}
