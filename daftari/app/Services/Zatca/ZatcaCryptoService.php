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

        // Invoice type flag: 4 booleans (Standard, Simplified, future, future),
        // matching ZATCA's documented "1000" = supports both tax + simplified.
        $invoiceType = $options['invoice_type'] ?? '1100';

        $key = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'secp256k1',
        ]);

        if ($key === false) {
            throw new RuntimeException('Unable to generate EC key pair: '.openssl_error_string());
        }

        openssl_pkey_export($key, $privateKeyPem);

        $configPath = $this->writeCsrConfig($egsSerial, $organizationUnit, $invoiceType, $addressLocation, $businessCategory);

        try {
            $dn = [
                'countryName' => $country,
                'organizationName' => $organizationName,
                'organizationalUnitName' => $organizationUnit,
                'commonName' => $commonName,
            ];

            $csr = openssl_csr_new($dn, $key, [
                'digest_alg' => 'sha256',
                'req_extensions' => 'v3_req',
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

    private function writeCsrConfig(string $egsSerial, string $vatNumber, string $invoiceType, string $location, string $businessCategory): string
    {
        $config = <<<CNF
oid_section = OIDS

[ OIDS ]
certificateTemplateName = 1.3.6.1.4.1.311.20.2

[ req ]
distinguished_name = req_distinguished_name
req_extensions = v3_req
prompt = no

[ req_distinguished_name ]

[ v3_req ]
basicConstraints = CA:FALSE
keyUsage = digitalSignature, nonRepudiation, keyEncipherment
certificateTemplateName = ASN1:UTF8String:ZATCA-Code-Signing
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
     * SHA-256 hash of the canonical invoice XML, base64-encoded — the
     * "current invoice hash" ZATCA requires in the QR/cryptographic stamp
     * and as the "PIH" (previous invoice hash) input for the next invoice.
     */
    public function hashInvoiceXml(string $canonicalXml): string
    {
        return base64_encode(hash('sha256', $canonicalXml, true));
    }

    /**
     * Signs the invoice hash with the company's own EC private key (the
     * same key used for its ZATCA CSID/CSR) — tag 7 of the Phase 2 QR.
     * Returns null until the company has generated a CSR/key pair.
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
        if (! openssl_sign(base64_decode($invoiceHashBase64), $signature, $key, OPENSSL_ALGO_SHA256)) {
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
