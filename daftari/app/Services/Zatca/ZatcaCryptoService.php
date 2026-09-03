<?php

namespace App\Services\Zatca;

use App\Models\Company;
use RuntimeException;

/**
 * Handles the cryptographic material ZATCA Phase 2 (Fatoora) requires:
 * an EC secp256k1 key pair, a CSR carrying ZATCA's custom subject/extension
 * fields (per the "Fatoora Simplified Tax Invoice" onboarding spec), and
 * SHA-256 invoice hashing with previous-invoice-hash chaining.
 */
class ZatcaCryptoService
{
    /**
     * ZATCA validates the CSR's certificateTemplateName against the
     * environment the OTP was issued for — sending the production name
     * ("ZATCA-Code-Signing") while onboarding against simulation (or vice
     * versa) is rejected outright as an invalid CSR.
     */
    private const CERT_TEMPLATE_NAMES = [
        'developer' => 'TSTZATCA-Code-Signing',
        'simulation' => 'PREZATCA-Code-Signing',
        'production' => 'ZATCA-Code-Signing',
    ];

    /**
     * Generates an EC secp256k1 private key and a CSR carrying the
     * ZATCA-specific subject fields (organization identity, VAT number,
     * EGS serial, invoice type flags) required by the compliance endpoint.
     *
     * @return array{private_key: string, csr: string}
     */
    public function generateCsr(Company $company, array $options = []): array
    {
        // Every one of these is company-editable (Settings → ZATCA →
        // onboarding) and stored on the company, not hardcoded here or
        // recomputed from the VAT number — Common Name and Organization
        // Unit Name are ZATCA's own free-text organizational identifiers,
        // distinct from the VAT registration number (which is already
        // carried separately as the UID field, see writeCsrConfig()).
        // $options still wins when passed explicitly (e.g. the
        // compliance-sample flow), for callers that don't go through a
        // persisted company record at all.
        $egsSerial = $options['egs_serial'] ?? ($company->zatca_egs_serial ?: ('1-Daftari|2-1.0.0|3-'.$company->id));
        $organizationName = $options['organization_name'] ?? $company->name;
        $organizationUnit = $options['organization_unit'] ?? ($company->zatca_organization_unit_name ?: $company->name);
        $commonName = $options['common_name'] ?? ($company->zatca_common_name ?: $company->name);
        // The VAT registration number is mandatory in the CSR's UID field
        // regardless of what Organization Unit Name is set to — it's no
        // longer the same value now that Organization Unit is its own
        // user-editable field rather than being computed from the VAT
        // number.
        $vatNumber = $options['vat_number'] ?? ($company->vat_number ?: '000000000000000');
        $country = 'SA';
        $businessCategory = $options['business_category'] ?? ($company->zatca_business_category ?: __('General'));
        $addressLocation = $options['location'] ?? (trim(($company->street_name ?: $company->address).' '.$company->city) ?: 'Riyadh');
        $certificateTemplateName = self::CERT_TEMPLATE_NAMES[$company->zatca_environment] ?? self::CERT_TEMPLATE_NAMES['developer'];

        // Invoice type flag: 4 booleans (Standard, Simplified, future,
        // future). Declaring a capability this company won't actually use
        // isn't free — ZATCA requires proving compliance for every
        // declared type (standard AND simplified each need their own
        // invoice/credit-note/debit-note compliance samples) before it
        // will issue a production CSID, so this follows the company's own
        // zatca_sync_b2b/zatca_sync_b2c settings rather than always
        // claiming both.
        $invoiceType = $options['invoice_type'] ?? (($company->zatca_sync_b2b ? '1' : '0').($company->zatca_sync_b2c ? '1' : '0').'00');

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);

        if ($key === false) {
            throw new RuntimeException('Unable to generate EC key pair: '.openssl_error_string());
        }

        openssl_pkey_export($key, $privateKeyPem);

        $configPath = $this->writeCsrConfig($egsSerial, $vatNumber, $invoiceType, $addressLocation, $businessCategory, $certificateTemplateName);

        try {
            $dn = [
                'commonName' => $commonName,
                'organizationalUnitName' => $organizationUnit,
                'organizationName' => $organizationName,
                'countryName' => $country,
            ];

            $csr = openssl_csr_new($dn, $key, [
                'digest_alg' => 'sha256',
                'req_extensions' => 'req_ext',
                'config' => $configPath,
            ]);

            if ($csr === false) {
                throw new RuntimeException('Unable to generate CSR: '.openssl_error_string());
            }

            openssl_csr_export($csr, $csrPem);
        } finally {
            @unlink($configPath);
        }

        return [
            'private_key' => $privateKeyPem,
            'csr' => base64_encode($csrPem),
        ];
    }

    private function writeCsrConfig(string $egsSerial, string $vatNumber, string $invoiceType, string $location, string $businessCategory, string $certificateTemplateName): string
    {
        $config = <<<CNF
oid_section = OIDs

[ OIDs ]
certificateTemplateName = 1.3.6.1.4.1.311.20.2

[ req ]
default_bits = 2048
distinguished_name = req_distinguished_name
req_extensions = req_ext
prompt = no

[ req_distinguished_name ]

[ req_ext ]
certificateTemplateName = ASN1:PRINTABLESTRING:{$certificateTemplateName}
subjectAltName = dirName:alt_names

[ alt_names ]
SN = {$egsSerial}
UID = {$vatNumber}
title = {$invoiceType}
registeredAddress = {$location}
businessCategory = {$businessCategory}
CNF;

        $path = tempnam(sys_get_temp_dir(), 'zatca_csr_').'.cnf';
        file_put_contents($path, $config);

        return $path;
    }


    /**
     * secp256k1's field size — 32 bytes — used to fix the length of each
     * half (r, s) of a raw ECDSA signature.
     */
    private const EC_COMPONENT_SIZE = 32;

    /**
     * Signs the invoice hash with the company's own EC private key (the
     * same key used for its ZATCA CSID/CSR) — tag 7 of the Phase 2 QR,
     * and also the source of the invoice's XAdES ds:SignatureValue (ZATCA's
     * Phase 2 signing profile uses this same simplified signature for
     * both, rather than a full enveloped-XMLDSig signature over the
     * canonicalized SignedInfo block). Signs the invoice hash's base64
     * *text* as the message — not the decoded raw hash bytes — per
     * ZATCA's documented signing behaviour. Returns null until the
     * company has generated a CSR/key pair.
     *
     * PHP's openssl_sign() always returns an EC signature as ASN.1 DER
     * (SEQUENCE of two INTEGERs) — the standard PKI/CMS convention. The
     * signature block declares SignatureMethod
     * "http://www.w3.org/2001/04/xmldsig-more#ecdsa-sha256", and per
     * RFC 4050 (which that xmldsig-more URI is defined against),
     * ds:SignatureValue for that algorithm must instead be the raw
     * concatenation of r and s as two fixed-length big-endian integers —
     * not DER. Embedding the DER bytes directly (this method's previous
     * behaviour) produces a signature ZATCA's validator can't verify
     * against the certificate's public key, surfacing as
     * "ECDSA Public Key does not match with qr code ECDSA public key"
     * even though the key itself is correct.
     */
    public function signInvoiceHash(Company $company, string $invoiceHashBase64): ?string
    {
        if (! $company->zatca_private_key) {
            return null;
        }

        $key = openssl_pkey_get_private($company->zatca_private_key);
        if ($key === false) {
            return null;
        }

        $der = null;
        if (! openssl_sign($invoiceHashBase64, $der, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return base64_encode($this->derSignatureToRaw($der));
    }

    /**
     * Converts an ASN.1 DER-encoded ECDSA signature (SEQUENCE of two
     * INTEGERs r, s) into the raw r||s concatenation XMLDSig's
     * ecdsa-sha256 algorithm requires: each component left-padded/
     * truncated to exactly self::EC_COMPONENT_SIZE bytes, with the
     * DER INTEGER's own sign-guard leading 0x00 byte (added whenever the
     * value's high bit would otherwise make it look negative) stripped.
     */
    private function derSignatureToRaw(string $der): string
    {
        $offset = 0;

        $readLength = function () use ($der, &$offset): int {
            $byte = ord($der[$offset++]);
            if (($byte & 0x80) === 0) {
                return $byte;
            }
            $numBytes = $byte & 0x7F;
            $length = 0;
            for ($i = 0; $i < $numBytes; $i++) {
                $length = ($length << 8) | ord($der[$offset++]);
            }

            return $length;
        };

        // SEQUENCE tag (0x30) + its length — the length itself is unused
        // here since each INTEGER carries its own.
        $offset++;
        $readLength();

        $readInteger = function () use ($der, &$offset, $readLength): string {
            $offset++; // INTEGER tag (0x02)
            $length = $readLength();
            $value = substr($der, $offset, $length);
            $offset += $length;

            $value = ltrim($value, "\x00");

            return str_pad($value, self::EC_COMPONENT_SIZE, "\x00", STR_PAD_LEFT);
        };

        $r = $readInteger();
        $s = $readInteger();

        return $r.$s;
    }

    /**
     * The very first invoice in a company's chain hashes an empty string
     * per ZATCA's documented base-case, exactly like a genesis block.
     */
    public function genesisHash(): string
    {
        return base64_encode(hash('sha256', '', true));
    }
}
