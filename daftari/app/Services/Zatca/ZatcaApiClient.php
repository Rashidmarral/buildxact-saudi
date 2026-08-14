<?php

namespace App\Services\Zatca;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP client over ZATCA's three real Fatoora gateway environments.
 * Base URLs and endpoint paths are exactly as published in ZATCA's
 * e-invoicing integration guide — nothing here is simulated except the
 * "developer" and "simulation" environments themselves, which ARE
 * ZATCA's own sandbox/simulation servers (not something Daftari fakes).
 */
class ZatcaApiClient
{
    public const BASE_URLS = [
        'developer' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal',
        'simulation' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/simulation',
        'production' => 'https://gw-fatoora.zatca.gov.sa/e-invoicing/core',
    ];

    public function baseUrl(string $environment): string
    {
        return self::BASE_URLS[$environment] ?? self::BASE_URLS['developer'];
    }

    /**
     * Step 1 of onboarding: exchange the CSR + OTP (from the ZATCA Fatoora
     * portal) for a compliance CSID (a certificate + secret used to
     * authenticate every subsequent call).
     */
    public function issueComplianceCsid(string $environment, string $csrBase64, string $otp): Response
    {
        return Http::withHeaders([
            'Accept-Version' => 'V2',
            'OTP' => $otp,
            'Accept-Language' => 'en',
        ])->post($this->baseUrl($environment).'/compliance', [
            'csr' => $csrBase64,
        ]);
    }

    /**
     * Step 2: run the compliance checks ZATCA requires before it will
     * issue a production CSID — submit sample invoices for validation.
     */
    public function checkComplianceInvoice(string $environment, string $csid, string $secret, string $invoiceXmlBase64, string $invoiceHash, string $uuid): Response
    {
        return $this->authenticated($environment, $csid, $secret)
            ->post($this->baseUrl($environment).'/compliance/invoices', [
                'invoiceHash' => $invoiceHash,
                'uuid' => $uuid,
                'invoice' => $invoiceXmlBase64,
            ]);
    }

    /**
     * Step 3: exchange the compliance CSID + a compliance request ID for
     * the long-lived production CSID.
     */
    public function issueProductionCsid(string $environment, string $csid, string $secret, string $complianceRequestId): Response
    {
        return $this->authenticated($environment, $csid, $secret)
            ->post($this->baseUrl($environment).'/production/csids', [
                'compliance_request_id' => $complianceRequestId,
            ]);
    }

    /**
     * B2B standard invoices go through clearance: ZATCA validates,
     * cryptographically stamps, and returns the stamp before the invoice
     * may be delivered to the buyer.
     */
    public function clearInvoice(string $environment, string $csid, string $secret, string $invoiceXmlBase64, string $invoiceHash, string $uuid): Response
    {
        return $this->authenticated($environment, $csid, $secret)
            ->withHeaders(['Clearance-Status' => '1'])
            ->post($this->baseUrl($environment).'/invoices/clearance/single', [
                'invoiceHash' => $invoiceHash,
                'uuid' => $uuid,
                'invoice' => $invoiceXmlBase64,
            ]);
    }

    /**
     * B2C simplified invoices are reported after delivery — no clearance
     * stamp is required back before the buyer receives the invoice.
     */
    public function reportInvoice(string $environment, string $csid, string $secret, string $invoiceXmlBase64, string $invoiceHash, string $uuid): Response
    {
        return $this->authenticated($environment, $csid, $secret)
            ->post($this->baseUrl($environment).'/invoices/reporting/single', [
                'invoiceHash' => $invoiceHash,
                'uuid' => $uuid,
                'invoice' => $invoiceXmlBase64,
            ]);
    }

    private function authenticated(string $environment, string $csid, string $secret)
    {
        return Http::withHeaders([
            'Accept-Version' => 'V2',
            'Accept-Language' => 'en',
        ])->withBasicAuth($csid, $secret)->timeout(30);
    }
}
