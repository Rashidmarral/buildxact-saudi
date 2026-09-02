<?php

namespace App\Services\Zatca;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Notifications\GenericNotification;
use App\Services\ZatcaQrGenerator;
use App\Support\DemoMode;
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
            + ZatcaDebitNoteLog::where('company_id', $company->id)->count()
            + 1;
    }

    /**
     * Invoices this company's current sync settings say should go to
     * ZATCA but have no successful (cleared/reported) log entry yet.
     */
    /**
     * ZATCA's XML monetary amounts are always rendered in the company's
     * base currency (SAR) regardless of DocumentCurrencyCode — a
     * foreign-currency invoice's amounts are only ever entered in that
     * foreign currency, so submitting one as-is would clear/report the
     * wrong figures. Until the generator is extended to do the conversion
     * itself, only base-currency invoices/credit notes are eligible.
     */
    public function pendingInvoices(Company $company)
    {
        return Invoice::where('company_id', $company->id)
            ->where('currency', $company->currency)
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
            ->where('currency', $company->currency)
            ->with('invoice')
            ->whereHas('invoice', function ($q) use ($company) {
                $q->when(! $company->zatca_sync_b2b, fn ($q) => $q->where('type', '!=', 'standard'))
                    ->when(! $company->zatca_sync_b2c, fn ($q) => $q->where('type', '!=', 'simplified'));
            })
            ->whereDoesntHave('zatcaCreditNoteLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
            ->get();
    }

    /**
     * A debit note follows the same B2B/B2C split as the invoice it raises
     * a charge against, same as a credit note.
     */
    public function isB2bDebitNote(DebitNote $debitNote): bool
    {
        return $debitNote->invoice->type === 'standard';
    }

    /**
     * Issued debit notes this company's sync settings say should go to
     * ZATCA but have no successful (cleared/reported) log entry yet.
     */
    public function pendingDebitNotes(Company $company)
    {
        return DebitNote::where('company_id', $company->id)
            ->where('status', 'issued')
            ->where('currency', $company->currency)
            ->with('invoice')
            ->whereHas('invoice', function ($q) use ($company) {
                $q->when(! $company->zatca_sync_b2b, fn ($q) => $q->where('type', '!=', 'standard'))
                    ->when(! $company->zatca_sync_b2c, fn ($q) => $q->where('type', '!=', 'simplified'));
            })
            ->whereDoesntHave('zatcaDebitNoteLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
            ->get();
    }

    public function submit(Invoice $invoice): ZatcaInvoiceLog
    {
        $company = $invoice->company;
        $environment = $company->zatca_environment;
        $isB2b = $this->isB2b($invoice);

        $csid = $company->zatcaCsidFor();
        $secret = $company->zatcaSecretFor();

        $uuid = $this->xml->newUuid();
        $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
        $unsignedXml = $this->xml->generate($invoice, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $this->nextIcv($company));
        $invoiceHash = $this->signer->contentHash($unsignedXml);

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

        // Never make the real outbound call for a demo company, even one
        // that's (hypothetically) fully onboarded — see App\Support\DemoMode.
        // Checked here rather than only in the controller so the scheduled
        // sync command (ZatcaSyncInvoices) is covered too, not just the
        // interactive "Sync now" button.
        if ($company->isDemo()) {
            $log->update([
                'status' => 'failed',
                'error_message' => DemoMode::zatcaSubmissions(),
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
                $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 1000);
                $log->update([
                    'status' => 'failed',
                    'response_payload' => $response->body(),
                    'error_message' => $errorMessage,
                ]);
                $this->notifyZatcaFailure($company, __('Invoice'), $invoice->invoice_number, $errorMessage);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
            $this->notifyZatcaFailure($company, __('Invoice'), $invoice->invoice_number, $e->getMessage());
        }

        return $log;
    }

    public function submitCreditNote(CreditNote $creditNote): ZatcaCreditNoteLog
    {
        $company = $creditNote->company;
        $environment = $company->zatca_environment;
        $isB2b = $this->isB2bCreditNote($creditNote);

        $csid = $company->zatcaCsidFor();
        $secret = $company->zatcaSecretFor();

        $uuid = $this->xml->newUuid();
        $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
        $unsignedXml = $this->xml->generateForCreditNote($creditNote, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $this->nextIcv($company));
        $invoiceHash = $this->signer->contentHash($unsignedXml);

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

        if ($company->isDemo()) {
            $log->update([
                'status' => 'failed',
                'error_message' => DemoMode::zatcaSubmissions(),
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
                $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 1000);
                $log->update([
                    'status' => 'failed',
                    'response_payload' => $response->body(),
                    'error_message' => $errorMessage,
                ]);
                $this->notifyZatcaFailure($company, __('Credit note'), $creditNote->credit_note_number, $errorMessage);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
            $this->notifyZatcaFailure($company, __('Credit note'), $creditNote->credit_note_number, $e->getMessage());
        }

        return $log;
    }

    public function submitDebitNote(DebitNote $debitNote): ZatcaDebitNoteLog
    {
        $company = $debitNote->company;
        $environment = $company->zatca_environment;
        $isB2b = $this->isB2bDebitNote($debitNote);

        $csid = $company->zatcaCsidFor();
        $secret = $company->zatcaSecretFor();

        $uuid = $this->xml->newUuid();
        $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
        $unsignedXml = $this->xml->generateForDebitNote($debitNote, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $this->nextIcv($company));
        $invoiceHash = $this->signer->contentHash($unsignedXml);

        $xmlString = $unsignedXml;
        $qrPng = null;

        if ($csid && $secret && $company->isZatcaOnboarded()) {
            [$xmlString, $qrPng] = $this->buildSignedPayload(
                $company, $unsignedXml, $invoiceHash, $csid,
                $debitNote->issue_date, (float) $debitNote->total, (float) $debitNote->vat_total,
            );
        }

        $log = ZatcaDebitNoteLog::create([
            'company_id' => $company->id,
            'debit_note_id' => $debitNote->id,
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

        if ($company->isDemo()) {
            $log->update([
                'status' => 'failed',
                'error_message' => DemoMode::zatcaSubmissions(),
            ]);

            return $log;
        }

        if ($qrPng) {
            $debitNote->update(['qr_code' => $qrPng]);
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
                $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 1000);
                $log->update([
                    'status' => 'failed',
                    'response_payload' => $response->body(),
                    'error_message' => $errorMessage,
                ]);
                $this->notifyZatcaFailure($company, __('Debit note'), $debitNote->debit_note_number, $errorMessage);
            }
        } catch (\Throwable $e) {
            $log->update([
                'status' => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
            $this->notifyZatcaFailure($company, __('Debit note'), $debitNote->debit_note_number, $e->getMessage());
        }

        return $log;
    }

    /**
     * Audit finding LOW-30: a failed clearance/reporting call left nothing
     * but a "failed" row in the sync log the user had to go looking for —
     * nobody was actually told a document didn't reach ZATCA. Only the
     * two branches below that represent a real attempted-and-rejected
     * submission notify; the "not onboarded yet" / demo-mode short
     * circuits above are expected states, not failures worth alerting on.
     */
    private function notifyZatcaFailure(Company $company, string $documentLabel, string $documentNumber, ?string $errorMessage): void
    {
        $body = __(':label :document failed to sync with ZATCA: :error', [
            'label' => $documentLabel,
            'document' => $documentNumber,
            'error' => Str::limit($errorMessage ?: __('Unknown error'), 150),
        ]);

        User::where('company_id', $company->id)
            ->get()
            ->filter(fn (User $user) => $user->hasPermission('zatca'))
            ->each(fn (User $user) => $user->notify(new GenericNotification(
                title: __('ZATCA sync failed'),
                body: $body,
                url: route('app.zatca.dashboard'),
                icon: 'zatca',
            )));
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
    // CRITICAL FIX: Generate signature ONCE and reuse for both QR and XML
    $signature = $this->crypto->signInvoiceHash($company, $invoiceHash);
    
    if (! $signature) {
        \Log::error('Failed to generate signature for invoice', [
            'company_id' => $company->id,
            'has_private_key' => !empty($company->zatca_private_key)
        ]);
        return [$unsignedXml, null];
    }

    try {
        $publicKey = $this->certificates->publicKeyBytes($certificateBase64);
        $certificateSignature = $this->certificates->certificateSignature($certificateBase64);
        
        \Log::debug('Certificate details for QR', [
            'public_key_length' => strlen($publicKey),
            'public_key_base64' => base64_encode($publicKey),
            'cert_signature_length' => strlen($certificateSignature),
        ]);
    } catch (\Throwable $e) {
        \Log::error('Failed to extract certificate data', [
            'error' => $e->getMessage()
        ]);
        return [$unsignedXml, null];
    }

    // CRITICAL FIX: For compliance samples, ensure date is not in future
    $now = new \DateTime('now', new \DateTimeZone('UTC'));
    $issueDateTime = clone $issueDate;
    $issueDateTime->setTimezone(new \DateTimeZone('UTC'));
    
    // If issue date is in future, use current date for compliance
    if ($issueDateTime > $now) {
        \Log::warning('Issue date is in future, adjusting to current date for compliance', [
            'issue_date' => $issueDateTime->format('Y-m-d H:i:s'),
            'current_date' => $now->format('Y-m-d H:i:s')
        ]);
        $issueDate = $now;
    }

    // Build QR TLV with the SAME data
    $qrTlv = ZatcaQrGenerator::buildTlvPayloadPhase2(
        $company->name,
        (string) $company->vat_number,
        $issueDate,
        $total,
        $vatTotal,
        $invoiceHash,
        $signature,  // Same signature
        base64_encode($publicKey),  // Encode the DER public key
        $certificateSignature,
    );

    if (! $qrTlv) {
        \Log::error('Failed to build QR TLV payload');
        return [$unsignedXml, null];
    }

    // Sign the XML using the SAME signature
    $signedXml = $this->signer->sign(
        $company, 
        $unsignedXml, 
        $invoiceHash, 
        $certificateBase64, 
        $qrTlv,  // QR payload with the same signature
        $signature  // Same signature
    );

    // Generate QR PNG with the SAME data
    $qrPng = ZatcaQrGenerator::generatePhase2(
        $company->name,
        (string) $company->vat_number,
        $issueDate,
        $total,
        $vatTotal,
        $invoiceHash,
        $signature,  // Same signature
        base64_encode($publicKey),  // Same encoding
        $certificateSignature,
    );

    return [$signedXml, $qrPng];
}
}
