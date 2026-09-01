<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaApiClient;
use App\Services\Zatca\ZatcaCryptoService;
use App\Services\Zatca\ZatcaSyncService;
use App\Services\Zatca\ZatcaXadesSigner;
use App\Services\Zatca\ZatcaXmlGenerator;
use App\Support\DemoMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ZatcaController extends Controller
{
    public function dashboard(Request $request)
    {
        $company = Auth::user()->company;
        $mode = $company->zatcaIntegrationMode();

        // The onboarding wizard, sync logs, and sync settings are only
        // meaningful once Phase 2 is actually the selected mode — skip
        // building them for Disabled/Phase 1 rather than running five
        // queries the view won't render anything from.
        if ($mode !== Company::ZATCA_MODE_PHASE2) {
            return view('user.zatca.dashboard', [
                'company' => $company,
                'mode' => $mode,
                'canUsePhase2' => $company->hasFeature('zatca_phase2'),
            ]);
        }

        $logs = ZatcaInvoiceLog::with('invoice')
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate(15, ['*'], 'invoice_page');

        $creditNoteLogs = ZatcaCreditNoteLog::with('creditNote')
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate(15, ['*'], 'credit_note_page');

        $debitNoteLogs = ZatcaDebitNoteLog::with('debitNote')
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate(15, ['*'], 'debit_note_page');

        $stats = [
            'cleared' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'cleared')->count(),
            'reported' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'reported')->count(),
            'failed' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'failed')->count(),
            'queued' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'queued')->count(),
        ];

        $sync = app(ZatcaSyncService::class);
        $pendingCount = $sync->pendingInvoices($company)->count();
        $pendingCreditNoteCount = $sync->pendingCreditNotes($company)->count();
        $pendingDebitNoteCount = $sync->pendingDebitNotes($company)->count();

        return view('user.zatca.dashboard', [
            'company' => $company,
            'mode' => $mode,
            'canUsePhase2' => true,
            'logs' => $logs,
            'creditNoteLogs' => $creditNoteLogs,
            'debitNoteLogs' => $debitNoteLogs,
            'stats' => $stats,
            'pendingCount' => $pendingCount,
            'pendingCreditNoteCount' => $pendingCreditNoteCount,
            'pendingDebitNoteCount' => $pendingDebitNoteCount,
            'environments' => ZatcaApiClient::BASE_URLS,
            'readiness' => $company->zatcaReadinessChecklist(),
        ]);
    }

    /**
     * The top-level Disabled / Phase 1 / Phase 2 switch — reachable
     * regardless of plan (see routes/web.php) so a company without the
     * zatca_phase2 feature can still see and choose between Disabled and
     * Phase 1. Selecting Phase 2 itself still requires the plan feature;
     * rejected server-side, not just hidden in the view, since the radio
     * value is otherwise just another form field.
     */
    public function updateMode(Request $request): RedirectResponse
    {
        $company = Auth::user()->company;

        $data = $request->validate([
            'zatca_integration_mode' => ['required', Rule::in(Company::ZATCA_MODES)],
        ]);

        if ($data['zatca_integration_mode'] === Company::ZATCA_MODE_PHASE2 && ! $company->hasFeature('zatca_phase2')) {
            return back()->withErrors(['zatca_integration_mode' => __('Phase 2 (FATOORA) requires a plan that includes ZATCA Phase 2 — upgrade your plan to enable it.')]);
        }

        $company->update(['zatca_integration_mode' => $data['zatca_integration_mode']]);

        return back()->with('status', __('ZATCA integration mode updated.'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $company = Auth::user()->company;

        $data = $request->validate([
            'zatca_environment' => ['required', 'in:developer,simulation,production'],
            'zatca_sync_frequency' => ['required', 'in:instant,hourly,daily,weekly,manual'],
            'zatca_sync_b2b' => ['nullable', 'boolean'],
            'zatca_sync_b2c' => ['nullable', 'boolean'],
        ]);

        $data['zatca_sync_b2b'] = $request->boolean('zatca_sync_b2b');
        $data['zatca_sync_b2c'] = $request->boolean('zatca_sync_b2c');

        if (! $data['zatca_sync_b2b'] && ! $data['zatca_sync_b2c']) {
            return back()->with('error', __('Enable at least one of Standard (B2B) or Simplified (B2C) invoicing.'));
        }

        // These two flags double as the invoice-type capability declared
        // to ZATCA in the CSR itself (via ZatcaCryptoService::generateCsr()):
        // declaring a capability that's actually off here creates
        // compliance-check requirements (and, per ZATCA's Simulation
        // environment, real validation trouble) for an invoice type the
        // company was never going to submit anyway. Reset onboarding on
        // change for the same reason environment changes do — the
        // previously issued CSR/CSID no longer matches what should be
        // declared.
        $capabilityChanged = $data['zatca_environment'] !== $company->zatca_environment
            || $data['zatca_sync_b2b'] !== (bool) $company->zatca_sync_b2b
            || $data['zatca_sync_b2c'] !== (bool) $company->zatca_sync_b2c;

        if ($capabilityChanged) {
            $data['zatca_onboarding_status'] = 'not_started';
            $data['zatca_csr'] = null;
            $data['zatca_private_key'] = null;
            $data['zatca_compliance_request_id'] = null;
            $data['zatca_compliance_csid'] = null;
            $data['zatca_compliance_secret'] = null;
            $data['zatca_production_request_id'] = null;
            $data['zatca_production_csid'] = null;
            $data['zatca_production_secret'] = null;
            $data['zatca_linked_at'] = null;
        }

        $company->update($data);

        return back()->with('status', __('ZATCA sync settings saved.'));
    }

    public function generateCsr(Request $request, ZatcaCryptoService $crypto): RedirectResponse
    {
        $company = Auth::user()->company;

        if ($company->isDemo()) {
            return back()->with('error', DemoMode::zatcaSubmissions());
        }

        if (! $company->isZatcaReady()) {
            return back()->with('error', __('Your company profile is missing information ZATCA requires (VAT number, CR number, or National Address). Complete the readiness checklist below, then generate the CSR.'));
        }

        // Common Name, Organization Unit Name, EGS Serial Number, and
        // Business Category are ZATCA's own free-text organizational
        // identifiers — they aren't derivable from anything else on the
        // company record (Organization Unit in particular is a branch/
        // group identifier, not the VAT number), so they're entered here
        // per company and persisted before generating, rather than
        // computed or hardcoded.
        $data = $request->validate([
            'zatca_common_name' => ['nullable', 'string', 'max:255'],
            'zatca_organization_unit_name' => ['nullable', 'string', 'max:255'],
            'zatca_egs_serial' => ['nullable', 'string', 'max:255'],
            'zatca_business_category' => ['nullable', 'string', 'max:255'],
        ]);
        $company->update($data);

        try {
            $result = $crypto->generateCsr($company);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('Could not generate the CSR: :error. This is almost always a server-side OpenSSL configuration issue, not your data — most commonly PHP cannot locate its openssl.cnf file (very common on Windows/XAMPP), or the OpenSSL build on this server does not support the secp256k1 curve ZATCA requires. Check the openssl section of php.ini (or set the OPENSSL_CONF environment variable to a valid openssl.cnf path) and try again.', ['error' => $e->getMessage()]));
        }

        $company->update([
            'zatca_csr' => $result['csr'],
            'zatca_private_key' => $result['private_key'],
            'zatca_onboarding_status' => 'csr_generated',
        ]);

        return back()->with('status', __('CSR and private key generated. Copy the CSR into the ZATCA Fatoora portal to request an OTP, then continue below.'));
    }

    public function issueComplianceCsid(Request $request, ZatcaApiClient $api): RedirectResponse
    {
        $company = Auth::user()->company;

        $request->validate(['otp' => ['required', 'string', 'max:20']]);

        if ($company->isDemo()) {
            return back()->with('error', DemoMode::zatcaSubmissions());
        }

        if (! $company->zatca_csr) {
            return back()->with('error', __('Generate a CSR first.'));
        }

        try {
            $response = $api->issueComplianceCsid($company->zatca_environment, $company->zatca_csr, $request->string('otp'));
        } catch (Throwable $e) {
            return back()->with('error', __('Could not reach ZATCA: :error', ['error' => $e->getMessage()]));
        }

        if (! $response->successful()) {
            return back()->with('error', __('ZATCA rejected the OTP/CSR (HTTP :code): :body', [
                'code' => $response->status(),
                'body' => $response->body(),
            ]));
        }

        $body = $response->json();

        $company->update([
            'zatca_compliance_request_id' => $body['requestID'] ?? $body['requestId'] ?? null,
            'zatca_compliance_csid' => $body['binarySecurityToken'] ?? null,
            'zatca_compliance_secret' => $body['secret'] ?? null,
            'zatca_onboarding_status' => 'compliance_pending',
        ]);

        return back()->with('status', __('Compliance CSID issued. Run the compliance checks next.'));
    }

    public function runComplianceCheck(ZatcaSyncService $sync, ZatcaCryptoService $crypto, ZatcaXmlGenerator $xml, ZatcaApiClient $api, ZatcaXadesSigner $signer): RedirectResponse
    {
        $company = Auth::user()->company;

        if ($company->isDemo()) {
            return back()->with('error', DemoMode::zatcaSubmissions());
        }

        if (! $company->zatca_compliance_csid || ! $company->zatca_compliance_secret) {
            return back()->with('error', __('Issue a compliance CSID first.'));
        }

        // ZATCA won't issue a production CSID until the EGS has proven it
        // can generate every document type/profile combination the CSR
        // itself declared support for (ZatcaCryptoService::generateCsr())
        // — tax invoice, credit note, and debit note, in the standard
        // (B2B) profile if zatca_sync_b2b, the simplified (B2C) profile
        // if zatca_sync_b2c, or both. Confirmed via a live
        // "Missing-ComplianceSteps" rejection naming exactly the
        // combinations for whichever profiles were declared.
        $combinations = [
            ...($company->zatca_sync_b2b ? [
                ['code' => '388', 'name' => '0100000', 'label' => __('Standard tax invoice')],
                ['code' => '381', 'name' => '0100000', 'label' => __('Standard credit note')],
                ['code' => '383', 'name' => '0100000', 'label' => __('Standard debit note')],
            ] : []),
            ...($company->zatca_sync_b2c ? [
                ['code' => '388', 'name' => '0200000', 'label' => __('Simplified tax invoice')],
                ['code' => '381', 'name' => '0200000', 'label' => __('Simplified credit note')],
                ['code' => '383', 'name' => '0200000', 'label' => __('Simplified debit note')],
            ] : []),
        ];

        $previousHash = $crypto->genesisHash();
        $failures = [];

        foreach ($combinations as $icv => $combo) {
            $uuid = $xml->newUuid();
            // One shared instant for both the XML's IssueDate/IssueTime and
            // the QR code's own timestamp (KSA-25) — computing them from
            // separate now() calls risks drift (canonicalization + signing
            // both take real time), which fails ZATCA's consistency check
            // between the two.
            $issuedAt = now();
            $unsignedXml = $xml->generateComplianceSample($company, $combo['code'], $combo['name'], $previousHash, $uuid, $icv + 1, $issuedAt);
            $hash = $signer->contentHash($unsignedXml);

            // ZATCA's compliance endpoint validates the same fully signed
            // XML (XAdES + embedded QR) that production submissions use,
            // not a plain document — the compliance CSID doubles as the
            // signing certificate for this step.
            [$xmlString] = $sync->buildSignedPayload(
                $company, $unsignedXml, $hash, $company->zatca_compliance_csid,
                $issuedAt, 4.60, 0.60,
            );

            try {
                $response = $api->checkComplianceInvoice(
                    $company->zatca_environment,
                    $company->zatca_compliance_csid,
                    $company->zatca_compliance_secret,
                    base64_encode($xmlString),
                    $hash,
                    $uuid
                );
            } catch (Throwable $e) {
                return back()->with('error', __('Could not reach ZATCA: :error', ['error' => $e->getMessage()]));
            }

            // A prior successful run already satisfied this exact
            // combination — ZATCA remembers that and refuses to accept it
            // again, but that's not a failure, it's already done.
            $alreadyCompliant = ! $response->successful() && Str::contains($response->body(), 'Submitted before');

            if (! $response->successful() && ! $alreadyCompliant) {
                $failures[] = $combo['label'].': HTTP '.$response->status().' — '.$response->body();
            }

            $previousHash = $hash;
        }

        if ($failures) {
            return back()->with('error', __('Compliance check failed for :count of 6 required document types: :errors', [
                'count' => count($failures),
                'errors' => implode(' | ', $failures),
            ]));
        }

        // The compliance CSID is only valid for this compliance-check call
        // itself — every environment (developer/simulation/production
        // alike) still needs the production CSID exchange next before
        // actual clearance/reporting submissions will be accepted; ZATCA
        // rejects those with a plain 401 otherwise. Confirmed against the
        // reference module's own onboarding flow, which requests the
        // production CSID unconditionally regardless of environment.
        $company->update(['zatca_onboarding_status' => 'compliance_verified']);

        return back()->with('status', __('Compliance checks passed. You can now request the production CSID.'));
    }

    public function issueProductionCsid(ZatcaApiClient $api): RedirectResponse
    {
        $company = Auth::user()->company;

        if ($company->isDemo()) {
            return back()->with('error', DemoMode::zatcaSubmissions());
        }

        // Normally gated on 'compliance_verified', but also allow retrying
        // from 'onboarded' with no zatca_production_csid yet — a state
        // reachable by any company that ran its compliance check before
        // this step existed, when onboarding used to (incorrectly) jump
        // straight to 'onboarded' from the compliance CSID alone. Without
        // this, those companies would be permanently stuck: the status
        // flag already says 'onboarded' so nothing offers this step again,
        // but no production CSID was actually ever issued.
        $eligible = $company->zatca_onboarding_status === 'compliance_verified'
            || ($company->zatca_onboarding_status === 'onboarded' && ! $company->zatca_production_csid);

        if (! $eligible) {
            return back()->with('error', __('Complete the compliance checks first.'));
        }

        try {
            $response = $api->issueProductionCsid(
                $company->zatca_environment,
                $company->zatca_compliance_csid,
                $company->zatca_compliance_secret,
                (string) $company->zatca_compliance_request_id
            );
        } catch (Throwable $e) {
            return back()->with('error', __('Could not reach ZATCA: :error', ['error' => $e->getMessage()]));
        }

        if (! $response->successful()) {
            $company->update(['zatca_onboarding_status' => 'failed']);

            return back()->with('error', __('Could not issue the production CSID (HTTP :code): :body', [
                'code' => $response->status(),
                'body' => $response->body(),
            ]));
        }

        $body = $response->json();

        $company->update([
            'zatca_production_request_id' => $body['requestID'] ?? $body['requestId'] ?? null,
            'zatca_production_csid' => $body['binarySecurityToken'] ?? null,
            'zatca_production_secret' => $body['secret'] ?? null,
            'zatca_onboarding_status' => 'onboarded',
            'zatca_linked_at' => now(),
        ]);

        return back()->with('status', __('This company is now onboarded with ZATCA for the :env environment.', ['env' => $company->zatca_environment]));
    }

    public function resetOnboarding(): RedirectResponse
    {
        Auth::user()->company->update([
            'zatca_onboarding_status' => 'not_started',
            'zatca_csr' => null,
            'zatca_private_key' => null,
            'zatca_compliance_request_id' => null,
            'zatca_compliance_csid' => null,
            'zatca_compliance_secret' => null,
            'zatca_production_request_id' => null,
            'zatca_production_csid' => null,
            'zatca_production_secret' => null,
            'zatca_linked_at' => null,
        ]);

        return back()->with('status', __('Onboarding reset. You can start again from CSR generation.'));
    }

    public function sync(ZatcaSyncService $sync): RedirectResponse
    {
        $company = Auth::user()->company;

        if (! $company->isZatcaOnboarded()) {
            return back()->with('error', __('Complete ZATCA onboarding before syncing invoices.'));
        }

        $invoices = $sync->pendingInvoices($company);
        $creditNotes = $sync->pendingCreditNotes($company);
        $debitNotes = $sync->pendingDebitNotes($company);

        if ($invoices->isEmpty() && $creditNotes->isEmpty() && $debitNotes->isEmpty()) {
            return back()->with('status', __('No pending invoices, credit notes, or debit notes to sync.'));
        }

        $cleared = 0;
        $failed = 0;

        foreach ($invoices as $invoice) {
            $log = $sync->submit($invoice);
            if (in_array($log->status, ['cleared', 'reported'], true)) {
                $cleared++;
            } else {
                $failed++;
            }
        }

        foreach ($creditNotes as $creditNote) {
            $log = $sync->submitCreditNote($creditNote);
            if (in_array($log->status, ['cleared', 'reported'], true)) {
                $cleared++;
            } else {
                $failed++;
            }
        }

        foreach ($debitNotes as $debitNote) {
            $log = $sync->submitDebitNote($debitNote);
            if (in_array($log->status, ['cleared', 'reported'], true)) {
                $cleared++;
            } else {
                $failed++;
            }
        }

        return back()->with('status', __(':cleared document(s) synced, :failed failed. See the log below.', ['cleared' => $cleared, 'failed' => $failed]));
    }
}
