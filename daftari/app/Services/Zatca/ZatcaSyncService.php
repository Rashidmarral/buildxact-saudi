<?php

namespace App\Services\Zatca;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\PrepaymentInvoice;
use App\Models\User;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Notifications\GenericNotification;
use App\Services\ZatcaQrGenerator;
use App\Support\DemoMode;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    public function isB2bPrepayment(PrepaymentInvoice $prepayment): bool
    {
        return $prepayment->type === 'standard';
    }

    /**
     * KSA's mandatory Invoice Counter Value (ICV) — an ever-incrementing
     * sequence across every document (invoice, credit note, debit note,
     * or prepayment invoice) submitted to ZATCA under the company's
     * currently active environment/CSID, distinct from the invoice's own
     * business-facing number and from the PIH hash chain.
     *
     * A pure read of Company::zatca_icv_counter — deliberately NOT a
     * COUNT(*) over the log tables the way this used to work. That
     * pooled every environment (developer/simulation/production)
     * together with no way to tell them apart, and never moved back to 1
     * when onboarding was reset for a fresh CSID, even though the hash
     * chain correctly does (see ZatcaController::resetOnboarding()) — an
     * inconsistent ICV/PIH pair ZATCA's clearance validation is built to
     * reject. The counter now lives and resets alongside the hash chain
     * (same company column, same per-environment save-slot via
     * Company::switchZatcaEnvironment(), same reset-to-0 on a fresh
     * CSID) and only ever advances in the exact update() call that
     * already advances zatca_last_invoice_hash on a CONFIRMED success —
     * see submit()/submitCreditNote()/submitDebitNote()/
     * submitPrepaymentInvoice() below — so a failed attempt never burns
     * an ICV value the hash chain didn't also move past.
     *
     * Must only ever be called from inside lockedSubmission()'s
     * transaction (every submit*() method below does this) — reading it
     * unlocked would let two concurrent submissions for the same company
     * compute the same ICV, or read the same previous-invoice-hash (PIH)
     * and build against a chain ZATCA no longer recognizes as current
     * once the first one is accepted. Security audit finding D-1.
     */
    public function nextIcv(Company $company): int
    {
        return $company->zatca_icv_counter + 1;
    }

    /**
     * ZATCA's business rule KSA-25 checks the QR code's own embedded
     * timestamp against this document's cbc:IssueDate/cbc:IssueTime.
     * ZatcaXmlGenerator::generate() builds those from $document->issue_date
     * (the calendar date only) for IssueDate and $document->created_at
     * (the real creation instant) for IssueTime — so the QR must encode
     * that exact same combination, not $document->issue_date alone, which
     * carries no time component (always midnight) and would silently
     * mismatch IssueTime on every real submission.
     */
    private function issuedAtFor(Invoice|CreditNote|DebitNote|PrepaymentInvoice $document): Carbon
    {
        return Carbon::parse($document->issue_date->format('Y-m-d').' '.($document->created_at ?? now('UTC'))->format('H:i:s'));
    }

    /**
     * Row-locks the company for the duration of one full submission (read
     * PIH/ICV through the outbound ZATCA call and the resulting hash
     * write-back) so concurrent submissions for the same company —
     * another queued instant-sync job, the batched zatca:sync-invoices
     * command, and an admin-initiated retry can all call submit*()
     * independently — serialize instead of racing. This is the single
     * choke point every one of those callers goes through, so locking
     * here protects all of them uniformly. The lock is legitimately held
     * across the HTTP call to ZATCA, not just the DB read/write: the
     * whole point of the hash chain is that submission N+1 cannot be
     * built until N's outcome (accepted or not) is known, so nothing else
     * touching this company's chain may proceed until this submission is
     * fully resolved. Mirrors Company::nextSequenceNumber()'s
     * lockForUpdate() pattern for the identical class of race in document
     * numbering. Security audit finding D-1.
     */
    private function lockedSubmission(int $companyId, \Closure $callback)
    {
        return DB::transaction(function () use ($companyId, $callback) {
            $company = Company::where('id', $companyId)->lockForUpdate()->first();

            return $callback($company);
        });
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
     * Mirrors pendingInvoices() exactly — same base-currency restriction,
     * same B2B/B2C sync toggles, same "no successful log yet" filter —
     * since a prepayment invoice is submitted through the identical
     * clearance/reporting pipeline.
     */
    public function pendingPrepaymentInvoices(Company $company)
    {
        return PrepaymentInvoice::where('company_id', $company->id)
            ->where('currency', $company->currency)
            ->where('status', 'issued')
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

    /**
     * A cancelled (or still-draft) invoice must never reach ZatcaXmlGenerator
     * — generate() has no way to represent "this invoice was cancelled" as
     * a real ZATCA document; it was found emitting a bare InvoiceTypeCode
     * 381 (credit note) with no BillingReference back to the original
     * invoice, which is not a valid way to void a cleared/reported invoice
     * under ZATCA's rules (that requires an actual credit note document).
     * pendingInvoices() already keeps bulk/scheduled sync away from these
     * statuses; this is the single choke point every submit() caller goes
     * through (including the one-off "Sync now" button, which had no
     * status check of its own), so guarding here covers all of them.
     * Returned unsaved — rejecting here is a local validation failure, not
     * a real attempt ZATCA ever saw, so it must not consume an ICV slot or
     * create a ZatcaInvoiceLog row.
     */
    /**
     * BR-KSA-23/24/69/78, BR-KSA-83: a Zero-rated, Exempt, or
     * Not-subject-to-VAT category line must carry an exemption reason in
     * the document's TaxSubtotal breakdown
     * (ZatcaXmlGenerator::appendDualTaxTotal()) — a fixed VATEX-SA-* code
     * for the first two, a free-text reason for the third (the spec has
     * no fixed code list for "Not subject to VAT"). TaxRate rows created
     * before this requirement existed (including the "VAT 0%"/"Exempt"
     * rates TaxRate::seedDefaults() auto-creates for every new company)
     * can still have none set, which would silently produce non-compliant
     * XML — caught here, at the single choke point every submit*() caller
     * goes through, rather than left to a clearance rejection from ZATCA
     * itself.
     */
    private function missingExemptionReasonError(iterable $items): ?string
    {
        foreach ($items as $item) {
            $taxRate = $item->taxRate;
            if (! $taxRate || ! $taxRate->needsExemptionReason()) {
                continue;
            }

            $missing = $taxRate->type === \App\Models\TaxRate::TYPE_NOT_SUBJECT
                ? ! $taxRate->exemption_reason_text
                : ! $taxRate->exemption_reason_code;

            if ($missing) {
                return __('Tax rate ":name" is missing a required ZATCA exemption reason. Set one in Settings → Tax rates before syncing.', ['name' => $taxRate->name]);
            }
        }

        return null;
    }

    /**
     * BR-KSA-81: a standard (B2B) tax invoice's buyer must carry the Other
     * Buyer ID (BT-46 — national ID/Iqama/passport/GCC ID, via
     * Client::additional_id_type) whenever it has no VAT registration
     * number — ZatcaXmlGenerator::party() only emits a PartyIdentification
     * element when one of those is actually set on the client, so a
     * standard-invoice buyer with neither would silently produce XML
     * missing a field ZATCA requires. Simplified (B2C) invoices aren't
     * covered by this rule, matching the spec's own "KSA-2, position 1
     * and 2 = 01" condition — isB2b()/isB2bCreditNote()/isB2bDebitNote()
     * already compute exactly that split for the sync endpoint choice.
     */
    private function missingBuyerIdentificationError(\App\Models\Client $client): ?string
    {
        if ($client->vat_number) {
            return null;
        }

        if ($client->additional_id_type && $client->additional_id_number) {
            return null;
        }

        return __('Client ":name" has no VAT number and no other buyer ID (national ID/Iqama/passport/GCC ID) — ZATCA requires one or the other on a standard tax invoice. Add one on the client record before syncing.', ['name' => $client->name]);
    }

    /**
     * BR-KSA-37/66: the seller's own building number and postal code, when
     * set at all, must be exactly 4 and 5 digits respectively — company
     * profile fields Settings never enforced a format on. Only checked
     * once a value is actually on file: these fields stayed optional right
     * up to this fix, so a company that simply hasn't filled one in yet
     * isn't newly blocked from syncing — only a value that's actually
     * present and malformed (e.g. "12" instead of "1234") is.
     */
    private function invalidSellerAddressFormatError(Company $company): ?string
    {
        if ($company->building_number && ! preg_match('/^\d{4}$/', (string) $company->building_number)) {
            return __('Your company building number must be exactly 4 digits for ZATCA (BR-KSA-37) — it is currently ":value". Fix it in Settings → Company profile before syncing.', ['value' => $company->building_number]);
        }

        if ($company->postal_code && ! preg_match('/^\d{5}$/', (string) $company->postal_code)) {
            return __('Your company postal code must be exactly 5 digits for ZATCA (BR-KSA-66) — it is currently ":value". Fix it in Settings → Company profile before syncing.', ['value' => $company->postal_code]);
        }

        return null;
    }

    /**
     * BR-KSA-63/67: once a buyer carries a country code of "SA" in the
     * XML — which ZatcaXmlGenerator::party() only ever emits alongside a
     * cac:PostalAddress, and only builds that address at all once at
     * least one of street/building number/district/city/postal code is
     * set (see $hasAddress there) — every one of those five fields
     * becomes mandatory, and the postal code must be 5 digits. A client
     * with none of the five on file never triggers this (no address, no
     * country code, rule doesn't fire — matches the spec's own "if the
     * buyer country code is SA" conditional); only a client with a
     * partial address is a genuine data gap worth blocking sync for.
     */
    private function incompleteBuyerAddressError(\App\Models\Client $client): ?string
    {
        $fields = [
            __('street') => $client->street_name,
            __('building number') => $client->building_number,
            __('district') => $client->district,
            __('city') => $client->city,
            __('postal code') => $client->postal_code,
        ];

        if (collect($fields)->every(fn ($value) => empty($value))) {
            return null;
        }

        $missing = collect($fields)->filter(fn ($value) => empty($value))->keys();

        if ($missing->isNotEmpty()) {
            return __('Client ":name" has a partial address — ZATCA requires street, building number, district, city and postal code together once any one of them is provided (BR-KSA-63). Missing: :fields. Complete it on the client record before syncing.', ['name' => $client->name, 'fields' => $missing->implode(', ')]);
        }

        if (! preg_match('/^\d{5}$/', (string) $client->postal_code)) {
            return __('Client ":name" has a postal code that is not exactly 5 digits (BR-KSA-67) — it is currently ":value". Fix it on the client record before syncing.', ['name' => $client->name, 'value' => $client->postal_code]);
        }

        return null;
    }

    private function rejectUnsubmittable(Invoice $invoice): ?ZatcaInvoiceLog
    {
        if (in_array($invoice->status, ['draft', 'cancelled'], true)) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => $invoice->status === 'cancelled'
                    ? __('A cancelled invoice cannot be synced to ZATCA. If it was already cleared/reported, void it with a credit note instead.')
                    : __('A draft invoice cannot be synced to ZATCA — issue it first.'),
            ]);
        }

        // Audit finding ZATCA-05: pendingInvoices() already excludes any
        // invoice with a cleared/reported log from bulk/scheduled sync,
        // but — same class of gap as the currency check above — the
        // single-document "Sync now" button and the admin retry action
        // had no guard of their own against resubmitting an
        // already-cleared invoice. A double-click or a retry after a slow
        // response would mint a fresh UUID/hash, advance the ICV, and fire
        // a second live clearance/report call for the same invoice.
        if ($invoice->isZatcaLocked()) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => __('This invoice has already been cleared or reported to ZATCA — resubmitting would create a duplicate clearance. If a correction is needed, issue a credit or debit note instead.'),
            ]);
        }

        if ($error = $this->missingExemptionReasonError($invoice->items()->with('taxRate')->get())) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($this->isB2b($invoice) && ($error = $this->missingBuyerIdentificationError($invoice->client))) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->invalidSellerAddressFormatError($invoice->company)) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->incompleteBuyerAddressError($invoice->client)) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        // Go-live compliance review finding: pendingInvoices() already
        // excludes a foreign-currency invoice from bulk/scheduled sync
        // (ZatcaXmlGenerator always renders amounts in the company's base
        // currency, so a foreign-currency document would clear/report the
        // wrong figures), but the single-document "Sync now" button and
        // the admin retry action had no currency check of their own —
        // same class of gap as the cancelled-invoice bug above.
        if ($invoice->currency !== $invoice->company->currency) {
            return new ZatcaInvoiceLog([
                'company_id' => $invoice->company_id,
                'invoice_id' => $invoice->id,
                'status' => 'failed',
                'error_message' => __('ZATCA sync only supports invoices in your base currency (:currency).', ['currency' => $invoice->company->currency]),
            ]);
        }

        return null;
    }

    /**
     * Mirrors rejectUnsubmittable() for the single pseudo-line a
     * prepayment invoice carries instead of a real item collection —
     * same cancelled/draft, exemption-reason, buyer-identification
     * (standard/B2B only), seller-address and buyer-address guards, same
     * base-currency restriction.
     */
    private function rejectUnsubmittablePrepayment(PrepaymentInvoice $prepayment): ?ZatcaInvoiceLog
    {
        if (in_array($prepayment->status, ['draft', 'cancelled'], true)) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => $prepayment->status === 'cancelled'
                    ? __('A cancelled prepayment invoice cannot be synced to ZATCA.')
                    : __('A draft prepayment invoice cannot be synced to ZATCA — issue it first.'),
            ]);
        }

        if ($prepayment->isZatcaSynced()) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => __('This prepayment invoice has already been cleared or reported to ZATCA — resubmitting would create a duplicate clearance.'),
            ]);
        }

        if ($error = $this->missingExemptionReasonError([$prepayment])) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($this->isB2bPrepayment($prepayment) && ($error = $this->missingBuyerIdentificationError($prepayment->client))) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->invalidSellerAddressFormatError($prepayment->company)) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->incompleteBuyerAddressError($prepayment->client)) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($prepayment->currency !== $prepayment->company->currency) {
            return new ZatcaInvoiceLog([
                'company_id' => $prepayment->company_id,
                'prepayment_invoice_id' => $prepayment->id,
                'status' => 'failed',
                'error_message' => __('ZATCA sync only supports invoices in your base currency (:currency).', ['currency' => $prepayment->company->currency]),
            ]);
        }

        return null;
    }

    /**
     * The single-document sync button and admin retry have no status
     * filter of their own (unlike pendingCreditNotes()/pendingDebitNotes(),
     * which already exclude void) — same class of gap as a cancelled
     * invoice reaching submit(). A voided credit/debit note was never
     * meant to go to ZATCA at all; letting it through would submit a real
     * document for something the business has marked invalid. Returned
     * unsaved, same as rejectUnsubmittable() — a local validation failure
     * ZATCA never saw must not consume an ICV slot or create a log row.
     */
    private function rejectUnsubmittableCreditNote(CreditNote $creditNote): ?ZatcaCreditNoteLog
    {
        if ($creditNote->status === 'void') {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => __('A voided credit note cannot be synced to ZATCA.'),
            ]);
        }

        if ($creditNote->isZatcaSynced()) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => __('This credit note has already been cleared or reported to ZATCA — resubmitting would create a duplicate clearance.'),
            ]);
        }

        if ($creditNote->currency !== $creditNote->company->currency) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => __('ZATCA sync only supports credit notes in your base currency (:currency).', ['currency' => $creditNote->company->currency]),
            ]);
        }

        // Go-live compliance review finding: a credit/debit note's
        // BillingReference points back to the original invoice's own
        // cbc:ID (and, when available, the UUID ZATCA assigned it at
        // clearance/reporting time) — see ZatcaXmlGenerator::generateFor-
        // CreditNote(). Submitting a correction for an invoice ZATCA has
        // no record of (never cleared/reported — still Phase 1 only, not
        // yet synced, or excluded by a B2B/B2C/currency setting) produces
        // a document whose BillingReference resolves to nothing on
        // ZATCA's side. Nothing previously stopped this; it was
        // reproducible against the existing test suite.
        if (! $creditNote->invoice->isZatcaLocked()) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => __('This credit note corrects an invoice that was never cleared or reported to ZATCA. Sync the original invoice first, then retry.'),
            ]);
        }

        if ($error = $this->missingExemptionReasonError($creditNote->items()->with('taxRate')->get())) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($this->isB2bCreditNote($creditNote) && ($error = $this->missingBuyerIdentificationError($creditNote->client))) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->invalidSellerAddressFormatError($creditNote->company)) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->incompleteBuyerAddressError($creditNote->client)) {
            return new ZatcaCreditNoteLog([
                'company_id' => $creditNote->company_id,
                'credit_note_id' => $creditNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        return null;
    }

    private function rejectUnsubmittableDebitNote(DebitNote $debitNote): ?ZatcaDebitNoteLog
    {
        if ($debitNote->status === 'void') {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => __('A voided debit note cannot be synced to ZATCA.'),
            ]);
        }

        if ($debitNote->isZatcaSynced()) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => __('This debit note has already been cleared or reported to ZATCA — resubmitting would create a duplicate clearance.'),
            ]);
        }

        if ($debitNote->currency !== $debitNote->company->currency) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => __('ZATCA sync only supports debit notes in your base currency (:currency).', ['currency' => $debitNote->company->currency]),
            ]);
        }

        if (! $debitNote->invoice->isZatcaLocked()) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => __('This debit note corrects an invoice that was never cleared or reported to ZATCA. Sync the original invoice first, then retry.'),
            ]);
        }

        if ($error = $this->missingExemptionReasonError($debitNote->items()->with('taxRate')->get())) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($this->isB2bDebitNote($debitNote) && ($error = $this->missingBuyerIdentificationError($debitNote->client))) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->invalidSellerAddressFormatError($debitNote->company)) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        if ($error = $this->incompleteBuyerAddressError($debitNote->client)) {
            return new ZatcaDebitNoteLog([
                'company_id' => $debitNote->company_id,
                'debit_note_id' => $debitNote->id,
                'status' => 'failed',
                'error_message' => $error,
            ]);
        }

        return null;
    }

    public function submit(Invoice $invoice): ZatcaInvoiceLog
    {
        if ($rejected = $this->rejectUnsubmittable($invoice)) {
            return $rejected;
        }

        return $this->lockedSubmission($invoice->company_id, function (Company $company) use ($invoice) {
            // Audit finding ZATCA-03: the check above runs before this
            // company row is locked — two concurrent submissions for the
            // SAME invoice (a double-click, a retry racing the original
            // request) can both pass it before either's transaction
            // commits. The company lock then serializes them, but without
            // re-checking here, the second one to acquire the lock still
            // goes on to mint a fresh UUID/ICV and fire a second live
            // clearance/report call for a document the first one, now
            // committed, already got cleared/reported. Re-running the
            // exact same check against a freshly-read invoice, now that
            // this company's submissions are serialized, closes the race.
            $invoice = $invoice->fresh();

            if ($rejected = $this->rejectUnsubmittable($invoice)) {
                return $rejected;
            }

            $environment = $company->zatca_environment;
            $isB2b = $this->isB2b($invoice);

            $csid = $company->zatcaCsidFor();
            $secret = $company->zatcaSecretFor();

            $uuid = $this->xml->newUuid();
            $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
            $icv = $this->nextIcv($company);
            $unsignedXml = $this->xml->generate($invoice, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $icv, $invoice->appliesPrepaymentInvoice, $invoice->prepaymentInvoiceApplications->loadMissing('prepaymentInvoice'));
            $invoiceHash = $this->signer->contentHash($unsignedXml);

            $xmlString = $unsignedXml;
            $qrPng = null;

            if ($csid && $secret && $company->isZatcaOnboarded()) {
                [$xmlString, $qrPng] = $this->buildSignedPayload(
                    $company, $unsignedXml, $invoiceHash, $csid,
                    $this->issuedAtFor($invoice), (float) $invoice->total, (float) $invoice->vat_total,
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
                    $company->update(['zatca_last_invoice_hash' => $invoiceHash, 'zatca_last_sync_at' => now(), 'zatca_icv_counter' => $icv]);

                    // Audit finding ZATCA-04: this used to save unconditionally,
                    // before the outbound call even ran — so a QR encoding a
                    // locally-signed, never-actually-cleared document stayed on
                    // the invoice even when ZATCA went on to reject it. The QR
                    // (and the signature/stamp it's built from) is only ever
                    // meaningful once ZATCA has genuinely confirmed this exact
                    // submission, so it's only persisted here, alongside the
                    // log's own "cleared"/"reported" status.
                    if ($qrPng) {
                        $invoice->update(['qr_code' => $qrPng]);
                    }
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
        });
    }

    /**
     * Mirrors submit() exactly for a prepayment (386) invoice — same
     * lockedSubmission() row-lock, same ICV/PIH chain (nextIcv() already
     * counts these rows since they share zatca_invoice_logs with regular
     * invoices), same clearance/reporting split by type, same demo-mode
     * and onboarding guards.
     */
    public function submitPrepaymentInvoice(PrepaymentInvoice $prepayment): ZatcaInvoiceLog
    {
        if ($rejected = $this->rejectUnsubmittablePrepayment($prepayment)) {
            return $rejected;
        }

        return $this->lockedSubmission($prepayment->company_id, function (Company $company) use ($prepayment) {
            // Audit finding ZATCA-03 — see submit()'s identical comment.
            $prepayment = $prepayment->fresh();

            if ($rejected = $this->rejectUnsubmittablePrepayment($prepayment)) {
                return $rejected;
            }

            $environment = $company->zatca_environment;
            $isB2b = $this->isB2bPrepayment($prepayment);

            $csid = $company->zatcaCsidFor();
            $secret = $company->zatcaSecretFor();

            $uuid = $this->xml->newUuid();
            $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
            $icv = $this->nextIcv($company);
            $unsignedXml = $this->xml->generateForPrepayment($prepayment, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $icv);
            $invoiceHash = $this->signer->contentHash($unsignedXml);

            $xmlString = $unsignedXml;
            $qrPng = null;

            if ($csid && $secret && $company->isZatcaOnboarded()) {
                [$xmlString, $qrPng] = $this->buildSignedPayload(
                    $company, $unsignedXml, $invoiceHash, $csid,
                    $this->issuedAtFor($prepayment), (float) $prepayment->total, (float) $prepayment->vat_amount,
                );
            }

            $log = ZatcaInvoiceLog::create([
                'company_id' => $company->id,
                'prepayment_invoice_id' => $prepayment->id,
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
                    $company->update(['zatca_last_invoice_hash' => $invoiceHash, 'zatca_last_sync_at' => now(), 'zatca_icv_counter' => $icv]);

                    // Audit finding ZATCA-04 — see submit()'s identical comment.
                    if ($qrPng) {
                        $prepayment->update(['qr_code' => $qrPng]);
                    }
                } else {
                    $errorMessage = Str::limit('HTTP '.$response->status().': '.$response->body(), 1000);
                    $log->update([
                        'status' => 'failed',
                        'response_payload' => $response->body(),
                        'error_message' => $errorMessage,
                    ]);
                    $this->notifyZatcaFailure($company, __('Prepayment invoice'), $prepayment->prepayment_number, $errorMessage);
                }
            } catch (\Throwable $e) {
                $log->update([
                    'status' => 'failed',
                    'error_message' => Str::limit($e->getMessage(), 1000),
                ]);
                $this->notifyZatcaFailure($company, __('Prepayment invoice'), $prepayment->prepayment_number, $e->getMessage());
            }

            return $log;
        });
    }

    public function submitCreditNote(CreditNote $creditNote): ZatcaCreditNoteLog
    {
        if ($rejected = $this->rejectUnsubmittableCreditNote($creditNote)) {
            return $rejected;
        }

        return $this->lockedSubmission($creditNote->company_id, function (Company $company) use ($creditNote) {
            // Audit finding ZATCA-03 — see submit()'s identical comment.
            $creditNote = $creditNote->fresh();

            if ($rejected = $this->rejectUnsubmittableCreditNote($creditNote)) {
                return $rejected;
            }

            $environment = $company->zatca_environment;
            $isB2b = $this->isB2bCreditNote($creditNote);

            $csid = $company->zatcaCsidFor();
            $secret = $company->zatcaSecretFor();

            $uuid = $this->xml->newUuid();
            $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
            $icv = $this->nextIcv($company);
            $unsignedXml = $this->xml->generateForCreditNote($creditNote, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $icv);
            $invoiceHash = $this->signer->contentHash($unsignedXml);

            $xmlString = $unsignedXml;
            $qrPng = null;

            if ($csid && $secret && $company->isZatcaOnboarded()) {
                [$xmlString, $qrPng] = $this->buildSignedPayload(
                    $company, $unsignedXml, $invoiceHash, $csid,
                    $this->issuedAtFor($creditNote), (float) $creditNote->total, (float) $creditNote->vat_total,
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
                    $company->update(['zatca_last_invoice_hash' => $invoiceHash, 'zatca_last_sync_at' => now(), 'zatca_icv_counter' => $icv]);

                    // Audit finding ZATCA-04 — see submit()'s identical comment.
                    if ($qrPng) {
                        $creditNote->update(['qr_code' => $qrPng]);
                    }
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
        });
    }

    public function submitDebitNote(DebitNote $debitNote): ZatcaDebitNoteLog
    {
        if ($rejected = $this->rejectUnsubmittableDebitNote($debitNote)) {
            return $rejected;
        }

        return $this->lockedSubmission($debitNote->company_id, function (Company $company) use ($debitNote) {
            // Audit finding ZATCA-03 — see submit()'s identical comment.
            $debitNote = $debitNote->fresh();

            if ($rejected = $this->rejectUnsubmittableDebitNote($debitNote)) {
                return $rejected;
            }

            $environment = $company->zatca_environment;
            $isB2b = $this->isB2bDebitNote($debitNote);

            $csid = $company->zatcaCsidFor();
            $secret = $company->zatcaSecretFor();

            $uuid = $this->xml->newUuid();
            $previousHash = $company->zatca_last_invoice_hash ?: $this->crypto->genesisHash();
            $icv = $this->nextIcv($company);
            $unsignedXml = $this->xml->generateForDebitNote($debitNote, $isB2b ? '0100000' : '0200000', $previousHash, $uuid, $icv);
            $invoiceHash = $this->signer->contentHash($unsignedXml);

            $xmlString = $unsignedXml;
            $qrPng = null;

            if ($csid && $secret && $company->isZatcaOnboarded()) {
                [$xmlString, $qrPng] = $this->buildSignedPayload(
                    $company, $unsignedXml, $invoiceHash, $csid,
                    $this->issuedAtFor($debitNote), (float) $debitNote->total, (float) $debitNote->vat_total,
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
                    $company->update(['zatca_last_invoice_hash' => $invoiceHash, 'zatca_last_sync_at' => now(), 'zatca_icv_counter' => $icv]);

                    // Audit finding ZATCA-04 — see submit()'s identical comment.
                    if ($qrPng) {
                        $debitNote->update(['qr_code' => $qrPng]);
                    }
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
        });
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
    public function buildSignedPayload(Company $company, string $unsignedXml, string $invoiceHash, string $certificateBase64, \DateTimeInterface $issueDate, float $total, float $vatTotal): array
    {
        $signature = $this->crypto->signInvoiceHash($company, $invoiceHash);

        if (! $signature) {
            Log::error('ZATCA: could not sign invoice hash — company has no private key on file', ['company_id' => $company->id]);

            return [$unsignedXml, null];
        }

        $publicKey = $this->certificates->publicKeyBytes($certificateBase64);
        $certificateSignature = $this->certificates->certificateSignature($certificateBase64);

        $qrTlv = ZatcaQrGenerator::buildTlvPayloadPhase2(
            $company->name,
            (string) $company->vat_number,
            $issueDate,
            $total,
            $vatTotal,
            $invoiceHash,
            $signature,
            $publicKey,
            $certificateSignature,
        );

        if (! $qrTlv) {
            return [$unsignedXml, null];
        }

        $signedXml = $this->signer->sign($company, $unsignedXml, $invoiceHash, $certificateBase64, $qrTlv, $signature);

        $qrPng = ZatcaQrGenerator::generatePhase2(
            $company->name,
            (string) $company->vat_number,
            $issueDate,
            $total,
            $vatTotal,
            $invoiceHash,
            $signature,
            $publicKey,
            $certificateSignature,
        );

        return [$signedXml, $qrPng];
    }
}
