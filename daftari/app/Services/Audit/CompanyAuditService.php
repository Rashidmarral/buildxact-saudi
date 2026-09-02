<?php

namespace App\Services\Audit;

use App\Models\Bill;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Services\Reports\FinancialReportService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Backs the "Company Audit" dashboard (User\CompanyAuditController) — a
 * self-service compliance/reconciliation view meant to let an owner (or
 * an outside auditor/ZATCA reviewer they hand access to) see, without
 * an accountant walking them through it, whether a fiscal year's books
 * are internally consistent and ZATCA-complete for that period.
 *
 * Every check here is a *consistency* check against data this app itself
 * already generated (the ledger, ZATCA sync logs, the company profile) —
 * it can't verify the numbers are correct against reality, only that
 * nothing looks structurally wrong (unbalanced entries, invoices that
 * never made it to the ledger or to ZATCA, a profile ZATCA would reject).
 * That's the same class of check a bookkeeper does before handing books
 * to an auditor, made automatic.
 */
class CompanyAuditService
{
    public function __construct(private readonly FinancialReportService $reports) {}

    /**
     * @return array{overall_status: string, sections: array<int, array{key: string, label: string, status: string, summary: string, items: Collection}>}
     */
    public function run(Company $company, Carbon $from, Carbon $to): array
    {
        $sections = array_values(array_filter([
            $this->booksBalanceSection($company, $from, $to),
            $this->ledgerPostingSection($company, $from, $to),
            $this->zatcaComplianceSection($company, $from, $to),
            $this->companyProfileSection($company),
        ]));

        $overallStatus = 'ok';
        foreach ($sections as $section) {
            if ($section['status'] === 'critical') {
                $overallStatus = 'critical';
                break;
            }
            if ($section['status'] === 'warning') {
                $overallStatus = 'warning';
            }
        }

        return ['overall_status' => $overallStatus, 'sections' => $sections];
    }

    /**
     * Every journal entry this app posts is built and saved inside one DB
     * transaction with debit/credit lines that must already sum equal
     * (LedgerPostingService::post() throws on an unbalanced entry before
     * it ever reaches the database) — so this should always pass. It's
     * still worth stating explicitly: it's the one line item a non
     * -accountant actually needs to hear in plain language ("yes, your
     * books balance"), and it would catch a corrupted row from outside
     * this app (a direct DB edit, a botched restore) that nothing else
     * here checks for.
     */
    private function booksBalanceSection(Company $company, Carbon $from, Carbon $to): array
    {
        $trialBalance = $this->reports->trialBalance($company, $from, $to);
        $totalDebit = round((float) $trialBalance->sum('debit'), 2);
        $totalCredit = round((float) $trialBalance->sum('credit'), 2);
        $balanced = abs($totalDebit - $totalCredit) < 0.01;

        return [
            'key' => 'books_balance',
            'label' => __('Books balance'),
            'status' => $balanced ? 'ok' : 'critical',
            'summary' => $balanced
                ? __('Total debits and credits match for this period.')
                : __('Total debits (:debit) do not match total credits (:credit) for this period.', ['debit' => number_format($totalDebit, 2), 'credit' => number_format($totalCredit, 2)]),
            'items' => collect(),
        ];
    }

    /**
     * Every document type below moves into its "posted" status
     * (Invoice: sent/paid/partially_paid, Bill: posted, Expense: approved,
     * CreditNote/DebitNote: issued) and calls the matching
     * LedgerPostingService::post*() method inside the same DB transaction
     * — so a posted-status document with no journal entry means something
     * broke that atomicity from outside the normal flow (a failed
     * migration, a direct DB edit), not a business situation a user
     * caused. Real, actionable finding if it ever fires.
     */
    private function ledgerPostingSection(Company $company, Carbon $from, Carbon $to): array
    {
        $postedSourceIds = fn (string $sourceType) => JournalEntry::where('company_id', $company->id)
            ->where('source_type', $sourceType)
            ->whereBetween('entry_date', [$from, $to])
            ->pluck('source_id');

        $missing = collect();

        Invoice::where('company_id', $company->id)
            ->whereIn('status', ['sent', 'paid', 'partially_paid'])
            ->whereBetween('issue_date', [$from, $to])
            ->whereNotIn('id', $postedSourceIds('invoice'))
            ->get()
            ->each(fn (Invoice $invoice) => $missing->push([
                'label' => __('Invoice :number', ['number' => $invoice->invoice_number]),
                'url' => route('app.invoices.show', $invoice),
            ]));

        Bill::where('company_id', $company->id)
            ->where('status', 'posted')
            ->whereBetween('bill_date', [$from, $to])
            ->whereNotIn('id', $postedSourceIds('bill'))
            ->get()
            ->each(fn (Bill $bill) => $missing->push([
                'label' => __('Bill :number', ['number' => $bill->bill_number]),
                'url' => route('app.bills.show', $bill),
            ]));

        Expense::where('company_id', $company->id)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from, $to])
            ->whereNotIn('id', $postedSourceIds('expense'))
            ->get()
            ->each(fn (Expense $expense) => $missing->push([
                'label' => __('Expense: :description', ['description' => $expense->description]),
                'url' => route('app.expenses.edit', $expense),
            ]));

        CreditNote::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$from, $to])
            ->whereNotIn('id', $postedSourceIds('credit_note'))
            ->get()
            ->each(fn (CreditNote $creditNote) => $missing->push([
                'label' => __('Credit note :number', ['number' => $creditNote->credit_note_number]),
                'url' => route('app.credit-notes.show', $creditNote),
            ]));

        DebitNote::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$from, $to])
            ->whereNotIn('id', $postedSourceIds('debit_note'))
            ->get()
            ->each(fn (DebitNote $debitNote) => $missing->push([
                'label' => __('Debit note :number', ['number' => $debitNote->debit_note_number]),
                'url' => route('app.debit-notes.show', $debitNote),
            ]));

        return [
            'key' => 'ledger_posting',
            'label' => __('Ledger posting integrity'),
            'status' => $missing->isEmpty() ? 'ok' : 'critical',
            'summary' => $missing->isEmpty()
                ? __('Every posted document in this period has a matching ledger entry.')
                : __(':count document(s) are marked posted but have no matching ledger entry — this needs technical investigation.', ['count' => $missing->count()]),
            'items' => $missing,
        ];
    }

    /**
     * Only rendered when Phase 2 is the company's active integration mode
     * (zatcaIntegrationMode()) — Phase 1/Disabled companies never submit
     * to ZATCA at all, so "documents ZATCA hasn't cleared yet" isn't a
     * finding for them, it's just how the product works.
     */
    private function zatcaComplianceSection(Company $company, Carbon $from, Carbon $to): ?array
    {
        if ($company->zatcaIntegrationMode() !== Company::ZATCA_MODE_PHASE2) {
            return null;
        }

        $unsynced = collect();

        Invoice::where('company_id', $company->id)
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('issue_date', [$from, $to])
            ->whereDoesntHave('zatcaInvoiceLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
            ->get()
            ->each(fn (Invoice $invoice) => $unsynced->push([
                'label' => __('Invoice :number', ['number' => $invoice->invoice_number]),
                'url' => route('app.invoices.show', $invoice),
            ]));

        CreditNote::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$from, $to])
            ->whereDoesntHave('zatcaCreditNoteLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
            ->get()
            ->each(fn (CreditNote $creditNote) => $unsynced->push([
                'label' => __('Credit note :number', ['number' => $creditNote->credit_note_number]),
                'url' => route('app.credit-notes.show', $creditNote),
            ]));

        DebitNote::where('company_id', $company->id)
            ->where('status', 'issued')
            ->whereBetween('issue_date', [$from, $to])
            ->whereDoesntHave('zatcaDebitNoteLogs', fn ($q) => $q->whereIn('status', ['cleared', 'reported']))
            ->get()
            ->each(fn (DebitNote $debitNote) => $unsynced->push([
                'label' => __('Debit note :number', ['number' => $debitNote->debit_note_number]),
                'url' => route('app.debit-notes.show', $debitNote),
            ]));

        return [
            'key' => 'zatca_compliance',
            'label' => __('ZATCA submission completeness'),
            'status' => $unsynced->isEmpty() ? 'ok' : 'warning',
            'summary' => $unsynced->isEmpty()
                ? __('Every eligible document in this period has been cleared or reported to ZATCA.')
                : __(':count document(s) in this period have not been cleared or reported to ZATCA yet.', ['count' => $unsynced->count()]),
            'items' => $unsynced,
        ];
    }

    /**
     * Reuses Company::zatcaReadinessChecklist() — the same VAT number/CR
     * number/National Address fields ZATCA requires on every tax invoice,
     * so a missing field here is exactly the kind of thing that blocks a
     * real audit regardless of ZATCA mode.
     */
    private function companyProfileSection(Company $company): array
    {
        $missing = collect($company->zatcaReadinessChecklist())
            ->reject(fn (array $check) => $check['ok'])
            ->map(fn (array $check) => ['label' => $check['label'], 'url' => route('app.settings.index')])
            ->values();

        return [
            'key' => 'company_profile',
            'label' => __('Company profile completeness'),
            'status' => $missing->isEmpty() ? 'ok' : 'warning',
            'summary' => $missing->isEmpty()
                ? __('All required company profile fields are complete.')
                : __(':count required company profile field(s) are missing.', ['count' => $missing->count()]),
            'items' => $missing,
        ];
    }
}
