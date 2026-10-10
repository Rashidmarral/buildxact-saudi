<?php

namespace App\Http\Controllers\User;

use App\Exceptions\PeriodLockedException;
use App\Http\Controllers\Controller;
use App\Http\Controllers\User\Concerns\ExportsCsv;
use App\Http\Controllers\User\Concerns\ResolvesReportPeriod;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Services\Accounting\LedgerPostingService;
use App\Services\Audit\CompanyAuditService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CompanyAuditController extends Controller
{
    use ExportsCsv, ResolvesReportPeriod;

    public function index(Request $request, CompanyAuditService $audit)
    {
        $company = Auth::user()->company;
        $period = $this->resolvePeriod($request);

        $result = $audit->run($company, $period['from'], $period['to']);

        if ($request->query('export') === 'csv') {
            return $this->csvResponse('company-audit.csv', [__('Section'), __('Status'), __('Finding'), __('Summary')],
                collect($result['sections'])->flatMap(function (array $section) {
                    if ($section['items']->isEmpty()) {
                        return [[$section['label'], ucfirst($section['status']), '', $section['summary']]];
                    }

                    return $section['items']->map(fn (array $item) => [$section['label'], ucfirst($section['status']), $item['label'], $section['summary']]);
                }));
        }

        if ($request->query('export') === 'pdf') {
            $pdf = Pdf::loadView('user.audit.pdf', [
                'company' => $company,
                'period' => $period,
                'overallStatus' => $result['overall_status'],
                'sections' => $result['sections'],
                'transactions' => $result['transactions'],
                'transactionTotals' => $result['transaction_totals'],
                'locale' => App::getLocale(),
            ]);

            return $pdf->download('company-audit-'.$period['from']->format('Y-m-d').'-to-'.$period['to']->format('Y-m-d').'.pdf');
        }

        return view('user.audit.index', [
            'company' => $company,
            'period' => $period,
            'overallStatus' => $result['overall_status'],
            'sections' => $result['sections'],
            'transactions' => $result['transactions'],
            'transactionTotals' => $result['transaction_totals'],
        ]);
    }

    /**
     * "1 document(s) are marked posted but have no matching ledger
     * entry — this needs technical investigation" — the ledger_posting
     * section finds exactly these documents; this lets an owner fix the
     * one they're looking at themselves, instead of it staying a
     * read-only finding with nowhere to act on it. Each post*() method
     * already no-ops (returns null) if a ledger entry already exists for
     * this document, so reposting something that's actually fine is
     * always safe.
     */
    public function repost(Request $request, LedgerPostingService $ledger)
    {
        $data = $request->validate([
            'source_type' => ['required', Rule::in(['invoice', 'bill', 'expense', 'credit_note', 'debit_note'])],
            'source_id' => ['required', 'integer'],
        ]);

        $companyId = Auth::user()->company_id;

        // Looked up before the try block so a document that doesn't
        // belong to this company (or doesn't exist) 404s the normal way,
        // rather than being caught below and reported as if posting
        // itself had failed — ModelNotFoundException extends RuntimeException.
        $document = match ($data['source_type']) {
            'invoice' => Invoice::where('company_id', $companyId)->findOrFail($data['source_id']),
            'bill' => Bill::where('company_id', $companyId)->findOrFail($data['source_id']),
            'expense' => Expense::where('company_id', $companyId)->findOrFail($data['source_id']),
            'credit_note' => CreditNote::where('company_id', $companyId)->findOrFail($data['source_id']),
            'debit_note' => DebitNote::where('company_id', $companyId)->findOrFail($data['source_id']),
        };

        try {
            $entry = match ($data['source_type']) {
                'invoice' => $ledger->postInvoiceIssued($document),
                'bill' => $ledger->postBillPosted($document),
                'expense' => $ledger->postExpense($document),
                'credit_note' => $ledger->postCreditNote($document),
                'debit_note' => $ledger->postDebitNote($document),
            };
        } catch (PeriodLockedException|\RuntimeException|\InvalidArgumentException $e) {
            return back()->with('status', $e->getMessage());
        }

        if (! $entry) {
            return back()->with('status', __('Nothing was posted — either this document already has a ledger entry under a different date, or every line amounted to zero.'));
        }

        AuditLog::record('ledger.repost', $entry, __('Reposted :type #:id to the ledger (entry :entry) after the Company Audit flagged it as missing', [
            'type' => $data['source_type'], 'id' => $data['source_id'], 'entry' => $entry->entry_number,
        ]));

        return back()->with('status', __('Posted to the ledger as entry :entry.', ['entry' => $entry->entry_number]));
    }
}
