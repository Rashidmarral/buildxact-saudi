<?php

namespace App\Services\Accounting;

use App\Models\BankTransfer;
use App\Models\Bill;
use App\Models\BillPayment;
use App\Models\CardSettlement;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CustomsDeclaration;
use App\Models\DebitNote;
use App\Models\EndOfServiceSettlement;
use App\Models\Expense;
use App\Models\FixedAsset;
use App\Models\FxRevaluation;
use App\Models\Income;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\PayrollRun;
use App\Models\PosSale;
use App\Models\ProjectCost;
use App\Models\ProjectMaterialIssue;
use App\Models\PurchaseReturn;
use App\Models\ReceiptVoucher;
use App\Models\StockAdjustment;
use App\Models\SubcontractorDeduction;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * A company that manually deletes a test document outside the app's own
 * void/cancel flows (e.g. straight from a database console) can still end
 * up with a correctly-reversed journal entry pair whose source document no
 * longer exists — net zero impact on every balance, but a permanent
 * "ghost" line in General Ledger / Journal Export referencing a document
 * that isn't there anymore. This is the exact pattern LedgerPostingService
 * itself never produces (every void/cancel path reverses in place, it
 * never deletes the document), so finding one of these always means a
 * document was removed by some route outside the controllers below —
 * direct DB access, a one-off script, or a future bug.
 *
 * Deliberately separate from LedgerPostingService: that class is the
 * normal day-to-day posting/reversal API every controller calls; this one
 * is a diagnostic-and-correction tool, only ever reached from the
 * super-admin "Ledger cleanup" panel, and only once Company::
 * hasAnyZatcaActivity() says the company has never touched ZATCA at all —
 * the one case where correcting historical postings can't contradict a
 * record the tax authority already has a copy of.
 */
class LedgerIntegrityService
{
    /**
     * source_type (with any '_reversal' suffix already stripped) => the
     * Eloquent model whose primary key a journal entry's source_id must
     * resolve to. Built directly from every `$this->post($company, 'x', ...)`
     * call site in LedgerPostingService — see that class if a new source
     * type is ever added there without a matching entry here.
     *
     * Deliberately excludes 'manual' (a manual journal entry IS the
     * record, not a reference to one) and the cheque-clearance/landed-
     * cost/settlement sub-postings that share their parent document's
     * source_id (receipt_voucher_cheque_clearance, payment_voucher_
     * cheque_clearance, payroll_run_settlement, customs_declaration_
     * landed_cost) — each already covered by its parent's own entry below.
     */
    private const SOURCE_MODELS = [
        'invoice' => Invoice::class,
        'invoice_payment' => InvoicePayment::class,
        'credit_note' => CreditNote::class,
        'debit_note' => DebitNote::class,
        'receipt_voucher' => ReceiptVoucher::class,
        'receipt_voucher_cheque_clearance' => ReceiptVoucher::class,
        'bill' => Bill::class,
        'bill_payment' => BillPayment::class,
        'payment_voucher' => PaymentVoucher::class,
        'payment_voucher_cheque_clearance' => PaymentVoucher::class,
        'fx_revaluation' => FxRevaluation::class,
        'purchase_return' => PurchaseReturn::class,
        'subcontractor_deduction' => SubcontractorDeduction::class,
        'payroll_run' => PayrollRun::class,
        'payroll_run_settlement' => PayrollRun::class,
        'end_of_service_settlement' => EndOfServiceSettlement::class,
        'pos_sale' => PosSale::class,
        'card_settlement' => CardSettlement::class,
        'expense' => Expense::class,
        'income' => Income::class,
        'customs_declaration' => CustomsDeclaration::class,
        'customs_declaration_landed_cost' => CustomsDeclaration::class,
        'stock_adjustment' => StockAdjustment::class,
        'project_material_issue' => ProjectMaterialIssue::class,
        'project_cost' => ProjectCost::class,
        'bank_transfer' => BankTransfer::class,
        'fixed_asset' => FixedAsset::class,
    ];

    /**
     * Every journal entry (original or reversal) whose source document no
     * longer exists, grouped so a reversal and its original are reported
     * together even though they're separate JournalEntry rows. Read-only —
     * safe to call unconditionally, including for a ZATCA-active company,
     * so the admin panel can always show what it found even while the
     * purge button itself stays disabled.
     *
     * @return Collection<int, array{entries: Collection<int, JournalEntry>, model: class-string, source_id: int, total_debit: float, total_credit: float}>
     */
    public function scanOrphans(Company $company): Collection
    {
        $entries = JournalEntry::where('company_id', $company->id)
            ->with('lines')
            ->get();

        $groups = [];

        foreach ($entries as $entry) {
            $baseType = str_ends_with($entry->source_type, '_reversal')
                ? substr($entry->source_type, 0, -strlen('_reversal'))
                : $entry->source_type;

            $modelClass = self::SOURCE_MODELS[$baseType] ?? null;

            if ($modelClass === null) {
                continue;
            }

            $groups[$baseType.':'.$entry->source_id]['model'] ??= $modelClass;
            $groups[$baseType.':'.$entry->source_id]['source_id'] ??= $entry->source_id;
            $groups[$baseType.':'.$entry->source_id]['entries'][] = $entry;
        }

        $result = collect();

        foreach ($groups as $group) {
            $modelClass = $group['model'];

            // A soft-deleted document (Invoice/ReceiptVoucher/PaymentVoucher/
            // Expense/Income/CustomsDeclaration/BankTransfer all use
            // SoftDeletes; the rest in SOURCE_MODELS don't) still "exists"
            // for this purpose — only a row that's gone from the table
            // entirely, hard-deleted, is an orphan. withTrashed() is only
            // defined on models using the trait, so it's only called there.
            $usesSoftDeletes = in_array(SoftDeletes::class, class_uses_recursive($modelClass), true);
            $query = $usesSoftDeletes ? $modelClass::withTrashed() : $modelClass::query();

            // Scoped to this company explicitly, not just by id: ids are
            // never reused across companies in practice (a single shared
            // auto-increment per table), but this runs for super_admin/
            // admin_staff, for whom BelongsToCompany's tenant scope is a
            // deliberate no-op (see that trait) — so nothing here may rely
            // on the query being scoped for us.
            if ($query->where('id', $group['source_id'])->where('company_id', $company->id)->exists()) {
                continue;
            }

            $groupEntries = collect($group['entries']);

            $result->push([
                'entries' => $groupEntries,
                'model' => $modelClass,
                'source_id' => $group['source_id'],
                'total_debit' => (float) $groupEntries->flatMap->lines->sum('debit'),
                'total_credit' => (float) $groupEntries->flatMap->lines->sum('credit'),
            ]);
        }

        return $result->sortByDesc(fn ($g) => $g['entries']->max('entry_date'))->values();
    }

    /**
     * Deletes every orphaned entry scanOrphans() finds, lines first. Hard-
     * blocked once the company has touched ZATCA in any way — see
     * Company::hasAnyZatcaActivity()'s own docblock for why that check is
     * broader than "are we live in production".
     *
     * Never touches a real, still-existing document or its (still valid)
     * postings — only entries already confirmed to reference a row that's
     * gone. Returns how many entries were removed and their combined gross
     * value, purely for the confirmation message; the whole point of an
     * orphan is that its debit and credit sides already net to zero.
     *
     * @return array{entries_removed: int, gross_amount: float}
     */
    public function purgeOrphans(Company $company): array
    {
        if ($company->hasAnyZatcaActivity()) {
            throw new RuntimeException('This company has ZATCA activity on file — its historical ledger entries cannot be corrected.');
        }

        $groups = $this->scanOrphans($company);

        return DB::transaction(function () use ($groups) {
            $entriesRemoved = 0;
            $grossAmount = 0.0;

            foreach ($groups as $group) {
                foreach ($group['entries'] as $entry) {
                    $grossAmount += (float) $entry->lines->sum('debit');
                    $entry->lines()->delete();
                    $entry->delete();
                    $entriesRemoved++;
                }
            }

            return ['entries_removed' => $entriesRemoved, 'gross_amount' => $grossAmount];
        });
    }
}
