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
 * hash it (chained to the company's previous invoice hash), sign it
 * (XAdES + QR embedding), submit it to the correct endpoint for its type
 * (clearance for standard/B2B, reporting for simplified/B2C), and record
 * the outcome in zatca_invoice_logs.
 */
class ZatcaSyncService
{
    public function __construct(
        private readonly ZatcaCryptoService $crypto,
        private readonly ZatcaXmlGenerator $xml,
        private readonly ZatcaApiClient $api,
        private readonly ZatcaCertificateService $certificates,
        private readonly ZatcaXadesSigner $signer,
    ) {}

    public function isB2b(Invoice $invoice): bool
    {
        return $invoice->type === 'standard';
    }

    /**
     * KSA's mandatory Invoice Counter Value (ICV) — a company-wide,
     * ever-incrementing sequence across every document (invoice or credit
     * note) ever submitted to ZATCA, distinct from the invoice's own
     * business-facing number and from the PIH hash chain.
     */
    public function nextIcv(Company $company): int
    {
        return ZatcaInvoiceLog::where('company_id', $company->id)->count()
            + ZatcaCreditNoteLog::where('company_id', $company->id)->count()
            + 1;
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
        $unsignedXml = $this->xml->generate($invoice, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $this->nextIcv($company));
        $invoiceHash = $this->crypto->hashInvoiceXml($unsignedXml);

        $xmlString = $unsignedXml;
        $qrPng = null;

        if ($csid && $secret && $company->isZatcaOnboarded()) {
            [$xmlString, $qrPng] = $this->buildSignedPayload(
                $company, $unsignedXml, $invoiceHash, $csid,
                $invoice->issue_date, (float) $invoice->total, (float) $invoice->vat_total,
            );
        }

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

        if ($qrPng) {
            $invoice->update(['qr_code' => $qrPng]);
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

    public function submitCreditNote(CreditNote $creditNote): ZatcaCreditNoteLog
    {
        $company = $creditNote->company;
        $environment = $company->zatca_environment;
        $isB2b = $this->isB2bCreditNote($creditNote);

        $csid = $company->zatcaCsidFor($environment);
        $secret = $company->zatcaSecretFor($environment);

        $uuid = $this->xml->newUuid();
        $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
        $unsignedXml = $this->xml->generateForCreditNote($creditNote, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $this->nextIcv($company));
        $invoiceHash = $this->crypto->hashInvoiceXml($unsignedXml);

        $xmlString = $unsignedXml;
        $qrPng = null;

        if ($csid && $secret && $company->isZatcaOnboarded()) {
            [$xmlString, $qrPng] = $this->buildSignedPayload(
                $company, $unsignedXml, $invoiceHash, $csid,
                $creditNote->issue_date, (float) $creditNote->total, (float) $creditNote->vat_total,
            );
        }

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

        if ($qrPng) {
            $creditNote->update(['qr_code' => $qrPng]);
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

    /**
     * Builds the fully signed XML (XAdES UBLExtensions + cac:Signature +
     * embedded QR) that actually gets submitted to ZATCA, and the QR PNG
     * shown in the UI — both built proactively from the company's own key
     * material and CSID certificate, not from a clearance response. QR
     * tag 9 ("certificate signature") is a property of the certificate
     * itself, not of any individual invoice, so — unlike the old
     * approach — nothing here depends on the submission having happened
     * yet or having succeeded.
     *
     * @return array{0: string, 1: ?string} [signedXml, qrPngBase64]
     */
    public function buildSignedPayload(Company $company, string $unsignedXml, string $invoiceHash, string $certificateBase64, \DateTimeInterface $issueDate, float $total, float $vatTotal): array
    {
        $signature = $this->crypto->signInvoiceHash($company, $invoiceHash);
        $publicKey = $this->certificates->publicKeyBytes($certificateBase64);
        $certificateSignature = $this->certificates->certificateSignature($certificateBase64);

        if (! $signature) {
            return [$unsignedXml, null];
        }

        $qrTlv = ZatcaQrGenerator::buildTlvPayloadPhase2(
            $company->name,
            (string) $company->vat_number,
            $issueDate,
            $total,
            $vatTotal,
            $invoiceHash,
            $signature,
            base64_encode($publicKey),
            $certificateSignature,
        );

        if (! $qrTlv) {
            return [$unsignedXml, null];
        }

        $signedXml = $this->signer->sign($company, $unsignedXml, $invoiceHash, $certificateBase64, $qrTlv);

        $qrPng = ZatcaQrGenerator::generatePhase2(
            $company->name,
            (string) $company->vat_number,
            $issueDate,
            $total,
            $vatTotal,
            $invoiceHash,
            $signature,
            base64_encode($publicKey),
            $certificateSignature,
        );

        return [$signedXml, $qrPng];
    }
}
