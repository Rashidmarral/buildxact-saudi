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
        $egsSerial = $options['egs_serial'] ?? ('1-Daftari|2-1.0.0|3-'.$company->id);
        $organizationName = $options['organization_name'] ?? $company->name;
        $organizationUnit = $company->vat_number ?: '000000000000000';
        $commonName = $options['common_name'] ?? ($company->vat_number ?: $company->name);
        $country = 'SA';
        $businessCategory = $options['business_category'] ?? __('General');
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

        $configPath = $this->writeCsrConfig($egsSerial, $organizationUnit, $invoiceType, $addressLocation, $businessCategory, $certificateTemplateName);

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
     * Signs the invoice hash with the company's own EC private key (the
     * same key used for its ZATCA CSID/CSR) — tag 7 of the Phase 2 QR,
     * and also the source of the invoice's XAdES ds:SignatureValue (ZATCA's
     * Phase 2 signing profile uses this same simplified signature for
     * both, rather than a full enveloped-XMLDSig signature over the
     * canonicalized SignedInfo block). Signs the invoice hash's base64
     * *text* as the message — not the decoded raw hash bytes — per
     * ZATCA's documented signing behaviour. Returns null until the
     * company has generated a CSR/key pair.
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

        $signature = null;
        if (! openssl_sign($invoiceHashBase64, $signature, $key, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        return base64_encode($signature);
    }

    /**
     * Raw EC public key point bytes (uncompressed SEC1 form: 0x04||X||Y)
     * derived from the company's stored private key — tag 8 of the Phase 2
     * QR. Returns null until the company has generated a CSR/key pair.
     */
    public function publicKeyBytes(Company $company): ?string
    {
        if (! $company->zatca_private_key) {
            return null;
        }

        $key = openssl_pkey_get_private($company->zatca_private_key);
        if ($key === false) {
            return null;
        }

        $details = openssl_pkey_get_details($key);
        if (empty($details['ec']['x']) || empty($details['ec']['y'])) {
            return null;
        }

        return base64_encode("\x04".$details['ec']['x'].$details['ec']['y']);
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
