<?php

namespace App\Services\Zatca;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Services\ZatcaQrGenerator;
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

    /**
     * A credit note follows the same B2B/B2C split as the invoice it
     * corrects — ZATCA only regulates the sales side, so this mirrors the
     * original invoice's type rather than being independently configurable.
     */
    public function isB2bCreditNote(CreditNote $creditNote): bool
    {
        return $creditNote->invoice->type === 'standard';
    }

    /**
     * Issued credit notes this company's sync settings say should go to
     * ZATCA but have no successful (cleared/reported) log entry yet.
     */
    public function pendingCreditNotes(Company $company)
    {
        return CreditNote::where('company_id', $company->id)
            ->where('status', 'issued')
            ->with('invoice')
            ->whereHas('invoice', function ($q) use ($company) {
                $q->when(! $company->zatca_sync_b2b, fn ($q) => $q->where('type', '!=', 'standard'))
                    ->when(! $company->zatca_sync_b2c, fn ($q) => $q->where('type', '!=', 'simplified'));
            })
            ->whereDoesntHave('zatcaCreditNoteLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
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
                $this->upgradeQr($invoice, $log->fresh());
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

    public function submitCreditNote(CreditNote $creditNote): ZatcaCreditNoteLog
    {
        $company = $creditNote->company;
        $environment = $company->zatca_environment;
        $isB2b = $this->isB2bCreditNote($creditNote);

        $csid = $company->zatcaCsidFor($environment);
        $secret = $company->zatcaSecretFor($environment);

        $uuid = $this->xml->newUuid();
        $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
        $xmlString = $this->xml->generateForCreditNote($creditNote, $isB2b ? '0100000' : '0200000', $previousHash, $uuid);
        $invoiceHash = $this->crypto->hashInvoiceXml($xmlString);

        $log = ZatcaCreditNoteLog::create([
            'company_id' => $company->id,
            'credit_note_id' => $creditNote->id,
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
                $this->upgradeCreditNoteQr($creditNote, $log->fresh());
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

    /**
     * Upgrades an invoice's QR from the Phase 1 5-tag style to the real
     * Phase 2 9-tag style once it has actually been cleared/reported.
     * Tags 6-8 are computed for real from the company's own ZATCA key
     * material; tag 9 is ZATCA's own cryptographic_stamp, passed through
     * exactly as received — never fabricated. Silently leaves the Phase 1
     * QR in place if any required component isn't available yet.
     */
    private function upgradeQr(Invoice $invoice, ZatcaInvoiceLog $log): void
    {
        if (! $log->cryptographic_stamp || ! $log->invoice_hash) {
            return;
        }

        $company = $invoice->company;
        $signature = $this->crypto->signInvoiceHash($company, $log->invoice_hash);
        $publicKey = $this->crypto->publicKeyBytes($company);

        if (! $signature || ! $publicKey) {
            return;
        }

        $qr = ZatcaQrGenerator::generatePhase2(
            $company->name,
            (string) $company->vat_number,
            $invoice->issue_date,
            (float) $invoice->total,
            (float) $invoice->vat_total,
            $log->invoice_hash,
            $signature,
            $publicKey,
            $log->cryptographic_stamp
        );

        if ($qr) {
            $invoice->update(['qr_code' => $qr]);
        }
    }

    private function upgradeCreditNoteQr(CreditNote $creditNote, ZatcaCreditNoteLog $log): void
    {
        if (! $log->cryptographic_stamp || ! $log->invoice_hash) {
            return;
        }

        $company = $creditNote->company;
        $signature = $this->crypto->signInvoiceHash($company, $log->invoice_hash);
        $publicKey = $this->crypto->publicKeyBytes($company);

        if (! $signature || ! $publicKey) {
            return;
        }

        $qr = ZatcaQrGenerator::generatePhase2(
            $company->name,
            (string) $company->vat_number,
            $creditNote->issue_date,
            (float) $creditNote->total,
            (float) $creditNote->vat_total,
            $log->invoice_hash,
            $signature,
            $publicKey,
            $log->cryptographic_stamp
        );

        if ($qr) {
            $creditNote->update(['qr_code' => $qr]);
        }
    }
}
