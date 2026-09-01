<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\ZatcaCreditNoteLog;
use App\Models\ZatcaDebitNoteLog;
use App\Models\ZatcaInvoiceLog;
use App\Services\Zatca\ZatcaApiClient;
use App\Services\Zatca\ZatcaSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Super Admin ZATCA management center (Module 15) — a platform-wide,
 * cross-company view over the ZATCA Phase 2 integration built in
 * app/Services/Zatca/* and app/Http/Controllers/User/ZatcaController.php.
 * This controller is entirely read/observe/retry: it never edits a
 * submitted invoice's data, never displays a private key, and every
 * mutating action (retry, test connection, re-onboarding reset) is
 * audit-logged. See the class-level docblocks on ZatcaSyncService and
 * ZatcaApiClient for what each reused piece actually does.
 */
class ZatcaController extends Controller
{
    /**
     * Exactly the field set App\Http\Controllers\User\ZatcaController::
     * resetOnboarding() already resets for a company's own owner — mirrored
     * here (not extracted into a shared helper) so this admin action stays
     * a simple, auditable duplicate of already-reviewed behavior rather
     * than a new code path the two controllers both depend on.
     */
    private const RESET_FIELDS = [
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
    ];

    public function index(Request $request)
    {
        $latestSubscriptionIds = Subscription::withoutGlobalScopes()
            ->selectRaw('MAX(id) as id')
            ->groupBy('company_id');
        $latestSubscriptions = Subscription::withoutGlobalScopes()
            ->whereIn('id', $latestSubscriptionIds)
            ->with('plan:id,has_zatca_phase2')
            ->get()
            ->keyBy('company_id');

        $stats = [
            'total_enabled' => $latestSubscriptions->filter(fn ($s) => (bool) $s->plan?->has_zatca_phase2)->count(),
            'connected' => Company::where('zatca_onboarding_status', 'onboarded')->whereNotNull('zatca_production_csid')->count(),
            'pending_onboarding' => Company::whereIn('zatca_onboarding_status', ['csr_generated', 'compliance_pending', 'compliance_verified'])->count(),
            'failed_connections' => Company::where('zatca_onboarding_status', 'failed')->count(),
        ];

        $submissionStats = $this->submissionStats();
        $stats = array_merge($stats, $submissionStats);

        $query = Company::query();

        if ($request->filled('q')) {
            $term = trim($request->q);
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('vat_number', 'like', "%{$term}%"));
        }

        if ($request->filled('environment')) {
            $query->where('zatca_environment', $request->string('environment'));
        }

        if ($request->filled('status')) {
            match ($request->string('status')->toString()) {
                'connected' => $query->where('zatca_onboarding_status', 'onboarded')->whereNotNull('zatca_production_csid'),
                'pending' => $query->whereIn('zatca_onboarding_status', ['csr_generated', 'compliance_pending', 'compliance_verified']),
                'failed' => $query->where('zatca_onboarding_status', 'failed'),
                'not_connected' => $query->where('zatca_onboarding_status', 'not_started'),
                default => null,
            };
        }

        $companies = $query->orderBy('name')->paginate(20)->withQueryString();

        [$lastSubmission, $lastSuccessful, $failedCounts] = $this->perCompanyAggregates($companies->pluck('id'));

        $recentErrors = $this->recentErrors();

        return view('admin.zatca.index', [
            'stats' => $stats,
            'companies' => $companies,
            'lastSubmission' => $lastSubmission,
            'lastSuccessful' => $lastSuccessful,
            'failedCounts' => $failedCounts,
            'recentErrors' => $recentErrors,
        ]);
    }

    public function logs(Request $request)
    {
        $companies = Company::orderBy('name')->get(['id', 'name']);

        $applyFilters = function ($query) use ($request) {
            if ($request->filled('company_id')) {
                $query->where('company_id', $request->integer('company_id'));
            }
            if ($request->filled('status')) {
                $query->where('status', $request->string('status'));
            }
            if ($request->filled('environment')) {
                $query->where('environment', $request->string('environment'));
            }
            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            return $query;
        };

        // Two separate paginated lists (rather than one merged/UNIONed
        // table) — mirroring the exact same split
        // App\Http\Controllers\User\ZatcaController::dashboard() already
        // uses for a single company's own invoice/credit-note logs, since
        // the two log tables have different foreign keys and don't share a
        // single sortable identity to paginate over.
        $invoiceLogs = $applyFilters(
            ZatcaInvoiceLog::withoutGlobalScopes()
                ->select(['id', 'company_id', 'invoice_id', 'environment', 'invoice_type', 'direction', 'status', 'request_uuid', 'error_message', 'submitted_at', 'cleared_at', 'created_at'])
                ->with(['company:id,name', 'invoice:id,invoice_number,type'])
        )->latest('id')->paginate(20, ['*'], 'invoice_page')->withQueryString();

        $creditNoteLogs = $applyFilters(
            ZatcaCreditNoteLog::withoutGlobalScopes()
                ->select(['id', 'company_id', 'credit_note_id', 'environment', 'invoice_type', 'direction', 'status', 'request_uuid', 'error_message', 'submitted_at', 'cleared_at', 'created_at'])
                ->with(['company:id,name', 'creditNote:id,credit_note_number'])
        )->latest('id')->paginate(20, ['*'], 'credit_note_page')->withQueryString();

        $debitNoteLogs = $applyFilters(
            ZatcaDebitNoteLog::withoutGlobalScopes()
                ->select(['id', 'company_id', 'debit_note_id', 'environment', 'invoice_type', 'direction', 'status', 'request_uuid', 'error_message', 'submitted_at', 'cleared_at', 'created_at'])
                ->with(['company:id,name', 'debitNote:id,debit_note_number'])
        )->latest('id')->paginate(20, ['*'], 'debit_note_page')->withQueryString();

        return view('admin.zatca.logs', compact('invoiceLogs', 'creditNoteLogs', 'debitNoteLogs', 'companies'));
    }

    public function showLog(string $type, int $log)
    {
        [$logModel, $document] = $this->resolveLog($type, $log);

        return view('admin.zatca.log-show', [
            'type' => $type,
            'log' => $logModel,
            'document' => $document,
        ]);
    }

    /**
     * The signed XML actually submitted to ZATCA for this log entry — the
     * same "View XML" artifact the company's own owner can already
     * download for a cleared/reported document (InvoiceController::
     * downloadXml / CreditNoteController::downloadXml), just reachable
     * from the admin side and not limited to successful submissions (a
     * failed attempt's XML is exactly what's useful to inspect here).
     */
    public function downloadXml(string $type, int $log)
    {
        [$logModel, $document] = $this->resolveLog($type, $log);

        abort_if(! $logModel->xml_payload, 404);

        $reference = match ($type) {
            'invoice' => $document->invoice_number ?? "log-{$logModel->id}",
            'credit_note' => $document->credit_note_number ?? "log-{$logModel->id}",
            'debit_note' => $document->debit_note_number ?? "log-{$logModel->id}",
        };

        return response($logModel->xml_payload, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => 'attachment; filename="'.$reference.'.xml"',
        ]);
    }

    /**
     * Resubmits the underlying invoice/credit note via the exact same
     * ZatcaSyncService::submit()/submitCreditNote() the normal sync flow
     * uses — this creates a brand-new log row, it never edits the failed
     * one or any submitted document data. Blocked once the document
     * already has a cleared/reported log, matching the same "no dangerous
     * modification of submitted invoice data" rule pendingInvoices()/
     * pendingCreditNotes() already enforce for the regular sync flow.
     */
    public function retry(string $type, int $log, ZatcaSyncService $sync)
    {
        [$logModel, $document] = $this->resolveLog($type, $log);

        abort_if(! $document, 404);

        $alreadyAccepted = $type === 'invoice' ? $document->isZatcaLocked() : $document->isZatcaSynced();

        if ($alreadyAccepted) {
            return back()->withErrors(['zatca' => __('This document has already been cleared or reported to ZATCA and cannot be resubmitted.')]);
        }

        $newLog = match ($type) {
            'invoice' => $sync->submit($document),
            'credit_note' => $sync->submitCreditNote($document),
            'debit_note' => $sync->submitDebitNote($document),
        };
        $accepted = in_array($newLog->status, ['cleared', 'reported'], true);

        AuditLog::record(
            'zatca.retry_submission',
            $logModel->company,
            __('Retried ZATCA submission for :type #:id (:company) — new status: :status', [
                'type' => match ($type) {
                    'invoice' => __('invoice'),
                    'credit_note' => __('credit note'),
                    'debit_note' => __('debit note'),
                },
                'id' => $document->id,
                'company' => $logModel->company?->name,
                'status' => $newLog->status,
            ]),
            companyId: $logModel->company_id,
        );

        return redirect()->route('admin.zatca.logs')->with(
            $accepted ? 'status' : 'error',
            $accepted
                ? __('Resubmitted successfully — new status: :status.', ['status' => $newLog->status])
                : __('Resubmission failed: :error', ['error' => $newLog->error_message ?? __('Unknown error')])
        );
    }

    /**
     * Network-level reachability check only — see
     * ZatcaApiClient::testConnection()'s docblock for exactly why this
     * doesn't (and shouldn't) simulate a real business call.
     */
    public function testConnection(Company $company, ZatcaApiClient $api)
    {
        $result = $api->testConnection($company->zatca_environment);

        AuditLog::record(
            'zatca.test_connection',
            $company,
            __('Tested ZATCA :env connectivity for :name: :result', [
                'env' => $company->zatca_environment,
                'name' => $company->name,
                'result' => $result['reachable'] ? "reachable (HTTP {$result['http_status']})" : 'unreachable: '.$result['error'],
            ]),
            companyId: $company->id,
        );

        return back()->with(
            $result['reachable'] ? 'status' : 'error',
            $result['reachable']
                ? __('ZATCA :env gateway is reachable (HTTP :status, :ms ms).', ['env' => $company->zatca_environment, 'status' => $result['http_status'], 'ms' => $result['latency_ms']])
                : __('Could not reach the ZATCA :env gateway: :error', ['env' => $company->zatca_environment, 'error' => $result['error']])
        );
    }

    /**
     * The admin-side equivalent of a company owner's own "Reset onboarding"
     * (App\Http\Controllers\User\ZatcaController::resetOnboarding) — for
     * when support needs to unstick a company that can't do it themselves.
     * Only ever clears the company's own onboarding state; never touches
     * already-submitted invoice/credit-note logs.
     */
    public function resetOnboarding(Company $company)
    {
        $previousStatus = $company->zatca_onboarding_status;

        // Deliberately audit only presence flags, never the actual
        // key/secret material — those stay encrypted-at-rest on the
        // company row and must never be copied into the audit log.
        $hadCredentials = [
            'had_csr' => (bool) $company->zatca_csr,
            'had_compliance_csid' => (bool) $company->zatca_compliance_csid,
            'had_production_csid' => (bool) $company->zatca_production_csid,
        ];

        $company->update(self::RESET_FIELDS);

        AuditLog::record(
            'zatca.reset_onboarding',
            $company,
            __('Reset ZATCA onboarding for :name (was: :status) — Super Admin support action', ['name' => $company->name, 'status' => $previousStatus]),
            old: ['zatca_onboarding_status' => $previousStatus] + $hadCredentials,
            new: ['zatca_onboarding_status' => 'not_started'],
            companyId: $company->id,
        );

        return back()->with('status', __('ZATCA onboarding reset for :name. The company can restart onboarding from CSR generation.', ['name' => $company->name]));
    }

    /**
     * @return array{0: \Illuminate\Support\Collection, 1: ?\App\Models\ZatcaInvoiceLog|?\App\Models\ZatcaCreditNoteLog|?\App\Models\ZatcaDebitNoteLog}
     */
    private function resolveLog(string $type, int $id): array
    {
        abort_unless(in_array($type, ['invoice', 'credit_note', 'debit_note'], true), 404);

        if ($type === 'invoice') {
            $log = ZatcaInvoiceLog::withoutGlobalScopes()->with(['company', 'invoice'])->findOrFail($id);

            return [$log, $log->invoice];
        }

        if ($type === 'credit_note') {
            $log = ZatcaCreditNoteLog::withoutGlobalScopes()->with(['company', 'creditNote'])->findOrFail($id);

            return [$log, $log->creditNote];
        }

        $log = ZatcaDebitNoteLog::withoutGlobalScopes()->with(['company', 'debitNote'])->findOrFail($id);

        return [$log, $log->debitNote];
    }

    /**
     * "Accepted" = cleared/reported. The two failure buckets below are a
     * categorization of OUR OWN stored error_message, not a ZATCA-native
     * status: every submit()/submitCreditNote() call in ZatcaSyncService
     * writes 'HTTP <code>: <body>' when ZATCA actually answered and
     * rejected the document, and writes the raw exception message (or the
     * "not onboarded yet" precheck message) when no response was ever
     * received. Splitting on that prefix distinguishes "ZATCA rejected it"
     * from "the submission never reached/completed against ZATCA" using
     * only data this system already records — nothing about ZATCA's own
     * API is invented here.
     */
    private function submissionStats(): array
    {
        $totalSubmitted = 0;
        $accepted = 0;
        $rejected = 0;
        $failedSubmissions = 0;

        foreach ([ZatcaInvoiceLog::class, ZatcaCreditNoteLog::class, ZatcaDebitNoteLog::class] as $model) {
            $totalSubmitted += $model::withoutGlobalScopes()->count();
            $accepted += $model::withoutGlobalScopes()->whereIn('status', ['cleared', 'reported'])->count();
            $rejected += $model::withoutGlobalScopes()->where('status', 'failed')->where('error_message', 'like', 'HTTP %')->count();
            $failedSubmissions += $model::withoutGlobalScopes()->where('status', 'failed')
                ->where(function ($q) {
                    $q->whereNull('error_message')->orWhere('error_message', 'not like', 'HTTP %');
                })->count();
        }

        return [
            'total_submitted' => $totalSubmitted,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'failed_submissions' => $failedSubmissions,
        ];
    }

    /**
     * Last submission time, last successful (cleared/reported) time, and
     * failed-submission count per company, across both log tables — two
     * grouped queries total (not N+1 per company), same pattern
     * Admin\CompanyController::index() already uses for its own per-page
     * aggregate columns.
     *
     * @return array{0: Collection, 1: Collection, 2: Collection}
     */
    private function perCompanyAggregates(Collection $companyIds): array
    {
        $lastSubmission = collect();
        $lastSuccessful = collect();
        $failedCounts = collect();

        foreach ([ZatcaInvoiceLog::class, ZatcaCreditNoteLog::class, ZatcaDebitNoteLog::class] as $model) {
            $model::withoutGlobalScopes()
                ->whereIn('company_id', $companyIds)
                ->selectRaw("company_id, MAX(submitted_at) as last_submission, MAX(cleared_at) as last_successful, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
                ->groupBy('company_id')
                ->get()
                ->each(function ($row) use ($lastSubmission, $lastSuccessful, $failedCounts) {
                    if ($row->last_submission) {
                        $lastSubmission[$row->company_id] = max($lastSubmission[$row->company_id] ?? '', $row->last_submission);
                    }
                    if ($row->last_successful) {
                        $lastSuccessful[$row->company_id] = max($lastSuccessful[$row->company_id] ?? '', $row->last_successful);
                    }
                    $failedCounts[$row->company_id] = ($failedCounts[$row->company_id] ?? 0) + (int) $row->failed_count;
                });
        }

        return [$lastSubmission, $lastSuccessful, $failedCounts];
    }

    /**
     * Latest failed submissions across both log tables, merged and
     * re-sorted in PHP (small N — capped at 10 — so this is simpler and
     * safer than a raw SQL UNION across two differently-shaped tables).
     */
    private function recentErrors(int $limit = 10): Collection
    {
        $invoiceErrors = ZatcaInvoiceLog::withoutGlobalScopes()
            ->where('status', 'failed')
            ->with(['company:id,name', 'invoice:id,invoice_number'])
            ->latest('id')->take($limit)->get()
            ->map(fn ($log) => [
                'company' => $log->company?->name,
                'reference' => $log->invoice?->invoice_number ?? "#{$log->invoice_id}",
                'type' => 'invoice',
                'log_id' => $log->id,
                'error' => $log->error_message,
                'created_at' => $log->created_at,
            ]);

        $creditNoteErrors = ZatcaCreditNoteLog::withoutGlobalScopes()
            ->where('status', 'failed')
            ->with(['company:id,name', 'creditNote:id,credit_note_number'])
            ->latest('id')->take($limit)->get()
            ->map(fn ($log) => [
                'company' => $log->company?->name,
                'reference' => $log->creditNote?->credit_note_number ?? "#{$log->credit_note_id}",
                'type' => 'credit_note',
                'log_id' => $log->id,
                'error' => $log->error_message,
                'created_at' => $log->created_at,
            ]);

        $debitNoteErrors = ZatcaDebitNoteLog::withoutGlobalScopes()
            ->where('status', 'failed')
            ->with(['company:id,name', 'debitNote:id,debit_note_number'])
            ->latest('id')->take($limit)->get()
            ->map(fn ($log) => [
                'company' => $log->company?->name,
                'reference' => $log->debitNote?->debit_note_number ?? "#{$log->debit_note_id}",
                'type' => 'debit_note',
                'log_id' => $log->id,
                'error' => $log->error_message,
                'created_at' => $log->created_at,
            ]);

        return $invoiceErrors->concat($creditNoteErrors)->concat($debitNoteErrors)->sortByDesc('created_at')->take($limit)->values();
    }
}
