<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaApiClient;
use App\Services\Zatca\ZatcaCryptoService;
use App\Services\Zatca\ZatcaSyncService;
use App\Services\Zatca\ZatcaXmlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

class ZatcaController extends Controller
{
    public function dashboard(Request $request)
    {
        $company = Auth::user()->company;

        $logs = ZatcaInvoiceLog::with('invoice')
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate(15, ['*'], 'invoice_page');

        $creditNoteLogs = ZatcaCreditNoteLog::with('creditNote')
            ->where('company_id', $company->id)
            ->latest('id')
            ->paginate(15, ['*'], 'credit_note_page');

        $stats = [
            'cleared' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'cleared')->count(),
            'reported' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'reported')->count(),
            'failed' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'failed')->count(),
            'queued' => ZatcaInvoiceLog::where('company_id', $company->id)->where('status', 'queued')->count(),
        ];

        $sync = app(ZatcaSyncService::class);
        $pendingCount = $sync->pendingInvoices($company)->count();
        $pendingCreditNoteCount = $sync->pendingCreditNotes($company)->count();

        return view('user.zatca.dashboard', [
            'company' => $company,
            'logs' => $logs,
            'creditNoteLogs' => $creditNoteLogs,
            'stats' => $stats,
            'pendingCount' => $pendingCount,
            'pendingCreditNoteCount' => $pendingCreditNoteCount,
            'environments' => ZatcaApiClient::BASE_URLS,
            'readiness' => $company->zatcaReadinessChecklist(),
        ]);
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

        if ($data['zatca_environment'] !== $company->zatca_environment) {
            // Each ZATCA environment has its own CSID pair — switching
            // means the previous onboarding no longer applies.
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

    public function generateCsr(ZatcaCryptoService $crypto): RedirectResponse
    {
        $company = Auth::user()->company;

        if (! $company->isZatcaReady()) {
            return back()->with('error', __('Your company profile is missing information ZATCA requires (VAT number, CR number, or National Address). Complete the readiness checklist below, then generate the CSR.'));
        }

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
                'body' => Str::limit($response->body(), 300),
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

    public function runComplianceCheck(ZatcaSyncService $sync, ZatcaCryptoService $crypto, ZatcaXmlGenerator $xml, ZatcaApiClient $api): RedirectResponse
    {
        $company = Auth::user()->company;

        if (! $company->zatca_compliance_csid || ! $company->zatca_compliance_secret) {
            return back()->with('error', __('Issue a compliance CSID first.'));
        }

        $invoice = $company->invoices()->whereNotIn('status', ['draft'])->latest('id')->first();

        if (! $invoice) {
            return back()->with('error', __('Create and send at least one invoice before running compliance checks.'));
        }

        $uuid = $xml->newUuid();
        $genesisHash = $crypto->genesisHash();
        $xmlString = $xml->generate($invoice, $sync->isB2b($invoice) ? '0100000' : '0200000', $genesisHash, $uuid);
        $hash = $crypto->hashInvoiceXml($xmlString);

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

        if (! $response->successful()) {
            return back()->with('error', __('Compliance check failed (HTTP :code): :body', [
                'code' => $response->status(),
                'body' => Str::limit($response->body(), 300),
            ]));
        }

        if ($company->zatca_environment === 'production') {
            $company->update(['zatca_onboarding_status' => 'compliance_verified']);

            return back()->with('status', __('Compliance checks passed. You can now request the production CSID.'));
        }

        // Developer/simulation environments don't require a separate
        // production CSID — the compliance CSID is the active credential.
        $company->update(['zatca_onboarding_status' => 'onboarded', 'zatca_linked_at' => now()]);

        return back()->with('status', __('This company is now onboarded with ZATCA for the :env environment.', ['env' => $company->zatca_environment]));
    }

    public function issueProductionCsid(ZatcaApiClient $api): RedirectResponse
    {
        $company = Auth::user()->company;

        if ($company->zatca_onboarding_status !== 'compliance_verified') {
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
                'body' => Str::limit($response->body(), 300),
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

        if ($invoices->isEmpty() && $creditNotes->isEmpty()) {
            return back()->with('status', __('No pending invoices or credit notes to sync.'));
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

        return back()->with('status', __(':cleared document(s) synced, :failed failed. See the log below.', ['cleared' => $cleared, 'failed' => $failed]));
    }
}
