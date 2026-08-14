<?php

namespace App\Services\Zatca;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\ZatcaInvoiceLog;
use Illuminate\Support\Str;

/**
 * Orchestrates one invoice's trip through ZATCA Phase 2: build the UBL XML,
 * hash it (chained to the company's previous invoice hash), submit it to
 * the correct endpoint for its type (clearance for standard/B2B, reporting
 * for simplified/B2C), and record the outcome in zatca_invoice_logs.
 */
class ZatcaSyncService
{
    public function __construct(
        private readonly ZatcaCryptoService $crypto,
        private readonly ZatcaXmlGenerator $xml,
        private readonly ZatcaApiClient $api,
    ) {}

    public function isB2b(Invoice $invoice): bool
    {
        return $invoice->type === 'standard';
    }

    /**
     * Invoices this company's current sync settings say should go to
     * ZATCA but have no successful (cleared/reported) log entry yet.
     */
    public function pendingInvoices(Company $company)
    {
        return Invoice::where('company_id', $company->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->when(! $company->zatca_sync_b2b, fn ($q) => $q->where('type', '!=', 'standard'))
            ->when(! $company->zatca_sync_b2c, fn ($q) => $q->where('type', '!=', 'simplified'))
            ->whereDoesntHave('zatcaInvoiceLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
            ->get();
    }

    public function submit(Invoice $invoice): ZatcaInvoiceLog
    {
        $company = $invoice->company;
        $environment = $company->zatca_environment;
        $isB2b = $this->isB2b($invoice);

        $csid = $company->zatcaCsidFor($environment);
        $secret = $company->zatcaSecretFor($environment);

        $uuid = $this->xml->newUuid();
        $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
        $xmlString = $this->xml->generate($invoice, $isB2b ? '0100000' : '0200000', $previousHash, $uuid);
        $invoiceHash = $this->crypto->hashInvoiceXml($xmlString);

        $log = ZatcaInvoiceLog::create([
            'company_id' => $company->id,
            'invoice_id' => $invoice->id,
            'environment' => $environment,
            'invoice_type' => $isB2b ? 'b2b' : 'b2c',
            'direction' => $isB2b ? 'clearance' : 'reporting',
            'status' => 'queued',
            'request_uuid' => $uuid,
            'invoice_hash' => $invoiceHash,
            'previous_invoice_hash' => $previousHash,
            'xml_payload' => $xmlString,
            'submitted_at' => now(),
        ]);

        if (! $csid || ! $secret || ! $company->isZatcaOnboarded()) {
            $log->update([
                'status' => 'failed',
                'error_message' => __('Company is not onboarded with ZATCA for the :env environment yet.', ['env' => $environment]),
            ]);

            return $log;
        }

        $xmlBase64 = base64_encode($xmlString);

        try {
            $response = $isB2b
                ? $this->api->clearInvoice($environment, $csid, $secret, $xmlBase64, $invoiceHash, $uuid)
                : $this->api->reportInvoice($environment, $csid, $secret, $xmlBase64, $invoiceHash, $uuid);

            if ($response->successful()) {
                $body = $response->json();
                $log->update([
                    'status' => $isB2b ? 'cleared' : 'reported',
                    'cryptographic_stamp' => $body['clearedInvoice'] ?? $body['reportingStatus'] ?? null,
                    'response_payload' => $response->body(),
                    'cleared_at' => now(),
                ]);
                $company->update(['zatca_last_invoice_hash' => $invoiceHash, 'zatca_last_sync_at' => now()]);
            } else {
                $log->update([
                    'status' => 'failed',
                    'response_payload' => $response->body(),
                    'error_message' => Str::limit('HTTP '.$response->status().': '.$response->body(), 1000),
                ]);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
        }

        return $log;
    }
}
