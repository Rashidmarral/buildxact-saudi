<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Bill;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Invoice;
use App\Models\PurchaseReturn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A self-diagnostic dashboard: three plain-language areas (Accounting
 * Setup, Financial Records, Transaction Processing) each backed by a real
 * query against this company's own data, not a canned status. Every issue
 * link goes to the actual page needed to fix it.
 *
 * Runs live on every request rather than caching a "last checked"
 * timestamp — the underlying queries are cheap (indexed lookups scoped to
 * one company), and a self-check that can go stale defeats the point of
 * having one.
 */
class AccountingHealthController extends Controller
{
    private const TOLERANCE = 0.01;

    public function index()
    {
        $company = Auth::user()->company;

        $setupIssues = $this->setupIssues($company->id);
        $financialIssues = $this->financialRecordIssues($company->id);
        $transactionIssues = $this->transactionIssues($company->id);

        $recentActivity = AuditLog::forCompany($company->id)
            ->whereIn('action', $this->accountingActions())
            ->latest('created_at')
            ->limit(10)
            ->get();

        return view('user.accounting.health', [
            'setupIssues' => $setupIssues,
            'financialIssues' => $financialIssues,
            'transactionIssues' => $transactionIssues,
            'issueCount' => count($setupIssues) + count($financialIssues) + count($transactionIssues),
            'recentActivity' => $recentActivity,
            'checkedAt' => now(),
        ]);
    }

    private function setupIssues(int $companyId): array
    {
        $issues = [];

        foreach (AccountMapping::catalog() as $key => $meta) {
            $account = AccountMapping::resolve($companyId, $key);

            if (! $account) {
                $issues[] = [
                    'title' => __('Required account mapping is missing.'),
                    'detail' => __(':label has no account assigned.', ['label' => __($meta['label'])]),
                    'technical_label' => __('Semantic account mappings'),
                    'action_label' => __('Configure Accounting'),
                    'action_route' => route('app.accounts.index'),
                ];
            } elseif (! $account->is_active) {
                $issues[] = [
                    'title' => __('A mapped account is inactive.'),
                    'detail' => __(':label points to :account, which is deactivated.', ['label' => __($meta['label']), 'account' => $account->code.' - '.$account->name]),
                    'technical_label' => __('Semantic account mappings'),
                    'action_label' => __('Configure Accounting'),
                    'action_route' => route('app.accounts.index'),
                ];
            }
        }

        return $issues;
    }

    private function financialRecordIssues(int $companyId): array
    {
        $issues = [];

        $totals = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $totalDebit = (float) $totals->total_debit;
        $totalCredit = (float) $totals->total_credit;

        if (abs($totalDebit - $totalCredit) > self::TOLERANCE) {
            $issues[] = [
                'title' => __('Trial balance does not balance.'),
                'detail' => __('Total debits (:debit) do not equal total credits (:credit) across all journal entries.', [
                    'debit' => number_format($totalDebit, 2),
                    'credit' => number_format($totalCredit, 2),
                ]),
                'technical_label' => __('Journal integrity'),
                'action_label' => __('View Trial Balance'),
                'action_route' => route('app.reports.trial-balance'),
            ];
        }

        $unbalancedEntryNumbers = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->where('journal_entries.company_id', $companyId)
            ->groupBy('journal_entries.id', 'journal_entries.entry_number')
            ->havingRaw('ABS(SUM(debit) - SUM(credit)) > ?', [self::TOLERANCE])
            ->pluck('journal_entries.entry_number');

        if ($unbalancedEntryNumbers->isNotEmpty()) {
            $issues[] = [
                'title' => __(':count journal entries do not balance.', ['count' => $unbalancedEntryNumbers->count()]),
                'detail' => __('Entries: :numbers', ['numbers' => $unbalancedEntryNumbers->take(5)->implode(', ')]),
                'technical_label' => __('Journal integrity'),
                'action_label' => __('View Journals'),
                'action_route' => route('app.journals.index'),
            ];
        }

        $inactiveAccountEntries = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.company_id', $companyId)
            ->where('accounts.is_active', false)
            ->distinct()
            ->count('journal_entries.id');

        if ($inactiveAccountEntries > 0) {
            $issues[] = [
                'title' => __(':count journal entries post to a deactivated account.', ['count' => $inactiveAccountEntries]),
                'detail' => __('These postings still count toward that account\'s balance, but the account is hidden from reports that only show active accounts.'),
                'technical_label' => __('Journal integrity'),
                'action_label' => __('View Chart of Accounts'),
                'action_route' => route('app.accounts.index', ['show_inactive' => 1]),
            ];
        }

        return $issues;
    }

    private function transactionIssues(int $companyId): array
    {
        $issues = [];

        $missingInvoices = Invoice::where('company_id', $companyId)
            ->whereIn('status', ['sent', 'partially_paid', 'paid'])
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'invoices.id')
                ->where('journal_entries.source_type', 'invoice'))
            ->count();

        if ($missingInvoices > 0) {
            $issues[] = $this->missingPostingIssue($missingInvoices, __('invoices'), route('app.invoices.index'));
        }

        $missingBills = Bill::where('company_id', $companyId)
            ->where('status', 'posted')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'bills.id')
                ->where('journal_entries.source_type', 'bill'))
            ->count();

        if ($missingBills > 0) {
            $issues[] = $this->missingPostingIssue($missingBills, __('bills'), route('app.bills.index'));
        }

        $missingCreditNotes = CreditNote::where('company_id', $companyId)
            ->where('status', 'issued')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'credit_notes.id')
                ->where('journal_entries.source_type', 'credit_note'))
            ->count();

        if ($missingCreditNotes > 0) {
            $issues[] = $this->missingPostingIssue($missingCreditNotes, __('credit notes'), route('app.credit-notes.index'));
        }

        $missingDebitNotes = DebitNote::where('company_id', $companyId)
            ->where('status', 'issued')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'debit_notes.id')
                ->where('journal_entries.source_type', 'debit_note'))
            ->count();

        if ($missingDebitNotes > 0) {
            $issues[] = $this->missingPostingIssue($missingDebitNotes, __('debit notes'), route('app.debit-notes.index'));
        }

        $missingPurchaseReturns = PurchaseReturn::where('company_id', $companyId)
            ->where('status', 'issued')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('journal_entries')
                ->whereColumn('journal_entries.source_id', 'purchase_returns.id')
                ->where('journal_entries.source_type', 'purchase_return'))
            ->count();

        if ($missingPurchaseReturns > 0) {
            $issues[] = $this->missingPostingIssue($missingPurchaseReturns, __('purchase returns'), route('app.purchase-returns.index'));
        }

        if (Schema::hasTable('failed_jobs')) {
            $failedJobs = DB::table('failed_jobs')->count();

            if ($failedJobs > 0) {
                $issues[] = [
                    'title' => __(':count background jobs failed.', ['count' => $failedJobs]),
                    'detail' => __('Some scheduled or queued work (e.g. ZATCA sync, recurring invoices) did not complete. Review and retry it.'),
                    'technical_label' => __('Failed jobs'),
                    'action_label' => null,
                    'action_route' => null,
                ];
            }
        }

        return $issues;
    }

    private function missingPostingIssue(int $count, string $label, string $route): array
    {
        return [
            'title' => __(':count :label are missing their accounting entry.', ['count' => $count, 'label' => $label]),
            'detail' => __('These documents are posted/issued but have no matching journal entry, so they will not appear in any financial report.'),
            'technical_label' => __('Unposted transactions'),
            'action_label' => __('View Details'),
            'action_route' => $route,
        ];
    }

    private function accountingActions(): array
    {
        return [
            'invoice.send', 'invoice.cancel', 'bill.create', 'bill.post', 'bill.void',
            'credit_note.create', 'debit_note.create', 'purchase_return.create', 'fixed_asset.create',
            'fixed_asset.dispose', 'budget.create', 'budget.activate', 'zakat.calculate',
            'expense.approve', 'expense.reject', 'stock_transfer.create', 'stock_transfer.reverse',
        ];
    }
}
