<?php

namespace App\Services\Admin;

use App\Models\AdvanceCredit;
use App\Models\Attachment;
use App\Models\BankReconciliation;
use App\Models\BankTransfer;
use App\Models\Bill;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CustomsDeclaration;
use App\Models\DebitNote;
use App\Models\DeliveryNote;
use App\Models\EndOfServiceSettlement;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\FxRevaluation;
use App\Models\Income;
use App\Models\InvoiceInstallment;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\KafeelPayment;
use App\Models\PaymentVoucher;
use App\Models\PayrollRun;
use App\Models\PrepaymentInvoice;
use App\Models\ProjectChangeOrder;
use App\Models\ProjectCost;
use App\Models\ProjectMaterialIssue;
use App\Models\ProjectSiteDiaryEntry;
use App\Models\ProjectTimesheet;
use App\Models\ProjectWorkOrder;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\PurchaseReturn;
use App\Models\Quotation;
use App\Models\ReceiptVoucher;
use App\Models\StockAdjustment;
use App\Models\Subcontract;
use App\Models\SubcontractorDeduction;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Super-admin "Company data reset": permanently deletes every selected
 * category of transaction record for ONE company — bills, vouchers,
 * expenses, bank transfers/reconciliations, purchase/sales documents
 * other than invoices, payroll, subcontracts, project activity, fixed
 * assets, stock adjustments, and manual journal entries — then rewinds
 * that category's document-number counter(s) back to their fresh-company
 * state, so the next document created starts again from number 1.
 *
 * Deliberately never touches: Invoices and their line items (the one
 * document type this tool is built to always leave alone), or any
 * master/reference data — customers, suppliers, items, employees,
 * projects themselves, the chart of accounts, bank account records. Only
 * the dated transactional activity recorded against them is wiped.
 *
 * Two hard limits worth being explicit about, because they're easy to
 * assume away:
 *  - This only ever resets the user-facing document NUMBER counter
 *    (next_bill_number and its siblings on Company) back to 1. It
 *    cannot and does not reset the underlying database row ID
 *    (auto-increment primary key) to 1 — that counter is shared by
 *    every company's rows in the same table, and rewinding it would
 *    corrupt every other tenant's data. The number a user actually
 *    sees on a document (BILL-00001, PV-00001, ...) is what resets;
 *    the invisible database id does not.
 *  - Blocked entirely once the company has touched ZATCA in any way
 *    (see Company::hasAnyZatcaActivity()) — credit/debit notes in
 *    particular are ZATCA-regulated documents, and wiping ledger
 *    history behind figures already on file with the tax authority
 *    is not something this tool will do.
 */
class CompanyDataResetService
{
    /**
     * One entry per wipeable category. 'models' lists every top-level
     * Eloquent model whose rows get force-deleted for this category —
     * every child row (line items, allocations, lines) is left to the
     * database's own ON DELETE CASCADE foreign keys rather than
     * enumerated here; see each table's migration for that cascade.
     * 'journal_source_types' are the JournalEntry.source_type values
     * (and their '_reversal' siblings) this category's postings use —
     * journal_entries.source_id is a plain column, not a foreign key,
     * so those rows need deleting explicitly rather than relying on a
     * cascade. 'number_columns' lists the Company counter(s) — and,
     * where it exists, the matching fiscal-year column — to rewind to
     * their fresh-company state once every row above is gone.
     *
     * @return array<string, array{label: string, group: string, models: array<int, class-string<Model>>, journal_source_types: array<int, string>, number_columns: array<int, array{number: string, fiscal?: string}>, extra_where?: Closure}>
     */
    public static function registry(): array
    {
        return [
            // Sales documents other than invoices.
            'credit_notes' => [
                'label' => __('Credit Notes'), 'group' => __('Sales'),
                'models' => [CreditNote::class], 'journal_source_types' => ['credit_note'],
                'number_columns' => [['number' => 'next_credit_note_number', 'fiscal' => 'credit_note_number_fiscal_year']],
            ],
            'debit_notes' => [
                'label' => __('Debit Notes'), 'group' => __('Sales'),
                'models' => [DebitNote::class], 'journal_source_types' => ['debit_note'],
                'number_columns' => [['number' => 'next_debit_note_number', 'fiscal' => 'debit_note_number_fiscal_year']],
            ],
            'invoice_payments' => [
                'label' => __('Invoice Payments & Installments'), 'group' => __('Sales'),
                'models' => [InvoicePayment::class, InvoiceInstallment::class], 'journal_source_types' => ['invoice_payment'],
                'number_columns' => [],
            ],
            'quotations' => [
                'label' => __('Quotations & Proformas'), 'group' => __('Sales'),
                'models' => [Quotation::class], 'journal_source_types' => [],
                'number_columns' => [
                    ['number' => 'next_quotation_number', 'fiscal' => 'quotation_number_fiscal_year'],
                    ['number' => 'next_proforma_number', 'fiscal' => 'proforma_number_fiscal_year'],
                ],
            ],
            'delivery_notes' => [
                'label' => __('Delivery Notes'), 'group' => __('Sales'),
                'models' => [DeliveryNote::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_delivery_note_number']],
            ],
            'prepayment_invoices' => [
                'label' => __('Prepayment Invoices'), 'group' => __('Sales'),
                'models' => [PrepaymentInvoice::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_prepayment_number', 'fiscal' => 'prepayment_number_fiscal_year']],
            ],

            // Purchasing documents.
            'bills' => [
                'label' => __('Bills'), 'group' => __('Purchasing'),
                'models' => [Bill::class], 'journal_source_types' => ['bill', 'bill_payment'],
                'number_columns' => [['number' => 'next_bill_number']],
            ],
            'purchase_orders' => [
                'label' => __('Purchase Orders'), 'group' => __('Purchasing'),
                'models' => [PurchaseOrder::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_po_number']],
            ],
            'purchase_requisitions' => [
                'label' => __('Purchase Requisitions'), 'group' => __('Purchasing'),
                'models' => [PurchaseRequisition::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_pr_number']],
            ],
            'purchase_returns' => [
                'label' => __('Purchase Returns'), 'group' => __('Purchasing'),
                'models' => [PurchaseReturn::class], 'journal_source_types' => ['purchase_return'],
                'number_columns' => [['number' => 'next_purchase_return_number']],
            ],

            // Cash & bank.
            'receipt_vouchers' => [
                'label' => __('Receipt Vouchers'), 'group' => __('Cash & Bank'),
                'models' => [ReceiptVoucher::class], 'journal_source_types' => ['receipt_voucher', 'receipt_voucher_cheque_clearance'],
                'number_columns' => [['number' => 'next_receipt_number']],
            ],
            'payment_vouchers' => [
                'label' => __('Payment Vouchers'), 'group' => __('Cash & Bank'),
                'models' => [PaymentVoucher::class], 'journal_source_types' => ['payment_voucher', 'payment_voucher_cheque_clearance'],
                'number_columns' => [['number' => 'next_payment_voucher_number']],
            ],
            'bank_transfers' => [
                'label' => __('Bank Transfers'), 'group' => __('Cash & Bank'),
                'models' => [BankTransfer::class], 'journal_source_types' => ['bank_transfer'],
                'number_columns' => [],
            ],
            'bank_reconciliations' => [
                'label' => __('Bank Reconciliations'), 'group' => __('Cash & Bank'),
                'models' => [BankReconciliation::class], 'journal_source_types' => [],
                'number_columns' => [],
            ],

            // Expenses & income.
            'expenses' => [
                'label' => __('Expenses'), 'group' => __('Expenses & Income'),
                'models' => [Expense::class], 'journal_source_types' => ['expense'],
                'number_columns' => [],
            ],
            'income' => [
                'label' => __('Income'), 'group' => __('Expenses & Income'),
                'models' => [Income::class], 'journal_source_types' => ['income'],
                'number_columns' => [],
            ],

            // Payroll & HR-related transactions.
            'payroll_runs' => [
                'label' => __('Payroll Runs'), 'group' => __('Payroll'),
                'models' => [PayrollRun::class], 'journal_source_types' => ['payroll_run', 'payroll_run_settlement'],
                'number_columns' => [['number' => 'next_payroll_run_number']],
            ],
            'end_of_service_settlements' => [
                'label' => __('End-of-Service Settlements'), 'group' => __('Payroll'),
                'models' => [EndOfServiceSettlement::class], 'journal_source_types' => ['end_of_service_settlement'],
                'number_columns' => [],
            ],
            'advance_credits' => [
                'label' => __('Advance Credits'), 'group' => __('Payroll'),
                'models' => [AdvanceCredit::class], 'journal_source_types' => [],
                'number_columns' => [],
            ],
            'kafeel_payments' => [
                'label' => __('Kafeel Payments'), 'group' => __('Payroll'),
                'models' => [KafeelPayment::class], 'journal_source_types' => [],
                'number_columns' => [],
            ],

            // Subcontracts.
            'subcontracts' => [
                'label' => __('Subcontracts & Subcontract Payments'), 'group' => __('Subcontracts'),
                'models' => [Subcontract::class], 'journal_source_types' => [],
                'number_columns' => [],
            ],
            'subcontractor_deductions' => [
                'label' => __('Subcontractor Deductions'), 'group' => __('Subcontracts'),
                'models' => [SubcontractorDeduction::class], 'journal_source_types' => ['subcontractor_deduction'],
                'number_columns' => [['number' => 'next_subcontractor_deduction_number']],
            ],

            // Project activity (the Project record itself is kept — only its dated activity is wiped).
            'project_costs' => [
                'label' => __('Project Costs & Material Issues'), 'group' => __('Projects'),
                'models' => [ProjectCost::class, ProjectMaterialIssue::class], 'journal_source_types' => ['project_cost', 'project_material_issue'],
                'number_columns' => [],
            ],
            'project_change_orders' => [
                'label' => __('Project Change Orders'), 'group' => __('Projects'),
                'models' => [ProjectChangeOrder::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_change_order_number']],
            ],
            'project_work_orders' => [
                'label' => __('Project Work Orders'), 'group' => __('Projects'),
                'models' => [ProjectWorkOrder::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_wo_number']],
            ],
            'project_timesheets' => [
                'label' => __('Project Timesheets & Site Diary'), 'group' => __('Projects'),
                'models' => [ProjectTimesheet::class, ProjectSiteDiaryEntry::class], 'journal_source_types' => [],
                'number_columns' => [],
            ],

            // Fixed assets & other GL-posting adjustments.
            'fixed_assets' => [
                'label' => __('Fixed Assets'), 'group' => __('Assets & Adjustments'),
                'models' => [FixedAsset::class], 'journal_source_types' => ['fixed_asset'],
                'number_columns' => [],
            ],
            'fx_revaluations' => [
                'label' => __('FX Revaluations'), 'group' => __('Assets & Adjustments'),
                'models' => [FxRevaluation::class], 'journal_source_types' => ['fx_revaluation'],
                'number_columns' => [],
            ],
            'customs_declarations' => [
                'label' => __('Customs Declarations'), 'group' => __('Assets & Adjustments'),
                'models' => [CustomsDeclaration::class], 'journal_source_types' => ['customs_declaration', 'customs_declaration_landed_cost'],
                'number_columns' => [],
            ],
            'stock_adjustments' => [
                'label' => __('Stock Adjustments'), 'group' => __('Assets & Adjustments'),
                'models' => [StockAdjustment::class], 'journal_source_types' => ['stock_adjustment'],
                'number_columns' => [],
            ],

            // General ledger.
            'manual_journal_entries' => [
                'label' => __('Manual Journal Entries'), 'group' => __('General Ledger'),
                'models' => [JournalEntry::class], 'journal_source_types' => [],
                'number_columns' => [['number' => 'next_journal_number']],
                'extra_where' => fn (Builder $query) => $query->where('source_type', 'manual'),
            ],
        ];
    }

    public static function groupedRegistry(): array
    {
        $grouped = [];

        foreach (self::registry() as $key => $category) {
            $grouped[$category['group']][$key] = $category;
        }

        return $grouped;
    }

    /**
     * Read-only row counts per category for this company — safe to call
     * unconditionally (including for a ZATCA-active company, so the
     * admin can still see what's there even though execute() will refuse
     * to touch it) so the confirmation screen can show exactly what each
     * checkbox commits to deleting before anything happens.
     *
     * @return array<string, int>
     */
    public function preview(Company $company): array
    {
        $counts = [];

        foreach (self::registry() as $key => $category) {
            $counts[$key] = collect($category['models'])
                ->sum(fn (string $modelClass) => $this->scopedQuery($modelClass, $company, $category['extra_where'] ?? null)->count());
        }

        return $counts;
    }

    /**
     * Permanently deletes every row in the selected categories for this
     * company, their associated journal entries, and any attachments on
     * the deleted documents (database row and stored file alike), then
     * rewinds each wiped category's document-number counter back to its
     * fresh-company state. All-or-nothing in a single transaction.
     *
     * @param  array<int, string>  $categoryKeys
     * @return array{deleted: array<string, int>, journal_entries_removed: int, attachments_removed: int}
     */
    public function execute(Company $company, array $categoryKeys): array
    {
        if ($company->hasAnyZatcaActivity()) {
            throw new RuntimeException('This company has ZATCA activity on file — its historical transaction records cannot be wiped.');
        }

        $registry = self::registry();
        $categoryKeys = array_values(array_intersect($categoryKeys, array_keys($registry)));

        if ($categoryKeys === []) {
            return ['deleted' => [], 'journal_entries_removed' => 0, 'attachments_removed' => 0];
        }

        return DB::transaction(function () use ($company, $registry, $categoryKeys) {
            $deleted = [];
            $journalEntriesRemoved = 0;
            $attachmentsRemoved = 0;
            $numberColumnUpdates = [];

            foreach ($categoryKeys as $key) {
                $category = $registry[$key];
                $categoryDeleted = 0;

                foreach ($category['models'] as $modelClass) {
                    [$count, $attachmentCount] = $this->deleteAllForCompany($modelClass, $company, $category['extra_where'] ?? null);
                    $categoryDeleted += $count;
                    $attachmentsRemoved += $attachmentCount;
                }

                $deleted[$key] = $categoryDeleted;

                if ($category['journal_source_types'] !== []) {
                    $journalEntriesRemoved += $this->purgeJournalEntriesFor($company, $category['journal_source_types']);
                }

                foreach ($category['number_columns'] as $col) {
                    $numberColumnUpdates[$col['number']] = 1;

                    if (! empty($col['fiscal'])) {
                        $numberColumnUpdates[$col['fiscal']] = null;
                        $numberColumnUpdates[$col['fiscal'].'_baseline'] = 0;
                    }
                }
            }

            if ($numberColumnUpdates !== []) {
                Company::withoutGlobalScopes()->whereKey($company->id)->update($numberColumnUpdates);
            }

            return [
                'deleted' => $deleted,
                'journal_entries_removed' => $journalEntriesRemoved,
                'attachments_removed' => $attachmentsRemoved,
            ];
        });
    }

    private function scopedQuery(string $modelClass, Company $company, ?Closure $extraWhere): Builder
    {
        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();
        $query->where('company_id', $company->id);

        if ($extraWhere !== null) {
            $extraWhere($query);
        }

        return $query;
    }

    /**
     * Deletes every row of $modelClass for this company — hard/force
     * delete regardless of whether the model uses SoftDeletes, since
     * this tool's whole point is a permanent wipe — plus any Attachment
     * rows (and their stored files) pointing at those rows. Child rows
     * (line items, allocations, ...) are left entirely to each table's
     * own ON DELETE CASCADE foreign key.
     *
     * @return array{0: int, 1: int} [rows deleted, attachments removed]
     */
    private function deleteAllForCompany(string $modelClass, Company $company, ?Closure $extraWhere): array
    {
        $ids = $this->scopedQuery($modelClass, $company, $extraWhere)->pluck('id');

        if ($ids->isEmpty()) {
            return [0, 0];
        }

        $morphClass = (new $modelClass)->getMorphClass();
        $attachments = Attachment::where('attachable_type', $morphClass)->whereIn('attachable_id', $ids)->get();

        foreach ($attachments as $attachment) {
            Storage::disk('public')->delete($attachment->path);
        }

        $attachmentCount = $attachments->count();
        Attachment::whereIn('id', $attachments->pluck('id'))->delete();

        $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
        $query = $modelClass::query()->whereIn('id', $ids);
        $deleted = $usesSoftDeletes ? $query->forceDelete() : $query->delete();

        return [$deleted, $attachmentCount];
    }

    /**
     * journal_entries.source_id is a plain column, not a foreign key (see
     * this class's own docblock), so a deleted source document's postings
     * are removed explicitly here rather than via cascade — both the
     * original entries and any '_reversal'-suffixed ones a void/cancel
     * created (see LedgerIntegrityService::SOURCE_MODELS for the same
     * convention).
     */
    private function purgeJournalEntriesFor(Company $company, array $sourceTypes): int
    {
        $allTypes = collect($sourceTypes)->flatMap(fn (string $t) => [$t, $t.'_reversal'])->all();

        $entryIds = JournalEntry::where('company_id', $company->id)->whereIn('source_type', $allTypes)->pluck('id');

        if ($entryIds->isEmpty()) {
            return 0;
        }

        JournalEntryLine::whereIn('journal_entry_id', $entryIds)->delete();

        return JournalEntry::whereIn('id', $entryIds)->delete();
    }
}
