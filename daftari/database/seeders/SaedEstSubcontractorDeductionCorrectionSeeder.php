<?php

namespace Database\Seeders;

use App\Models\Bill;
use App\Models\Company;
use App\Models\PurchaseReturn;
use App\Models\SubcontractorDeduction;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * "purchase return we have done in a wrong sense please fix all" —
 * PR-00001 against the SAED EST bill was never really a Purchase
 * Return: nothing was ever bought from Shahid to return to him. It
 * reused that document type only because it happened to post the
 * right debits and credits (Dr Accounts Payable, Cr Operating
 * Expense) for crediting his bill with the net cost of material the
 * company bought from Al Jazeera and Dar Al Manajim and supplied to
 * him — the correct label is a Subcontractor Deduction (see
 * SubcontractorDeduction, a real document type, not a repurposed one).
 *
 * This voids the original PurchaseReturn (reversing its journal entry,
 * keeping the document for the audit trail — never deleted) and
 * records the exact same SAR 134,395.68 as a proper Subcontractor
 * Deduction instead. The GL effect is identical: the same Dr Accounts
 * Payable / Cr Operating Expense amount, on SAED EST's bill, dated the
 * same day — only the document type and its label change, not a
 * single riyal of the real correction already applied.
 *
 * Run it yourself, after taking a database backup, once
 * SaedEstMaterialSupplierCorrectionSeeder has already run:
 *   php artisan db:seed --class="Database\\Seeders\\SaedEstSubcontractorDeductionCorrectionSeeder"
 *
 * Idempotent: does nothing if the Subcontractor Deduction already exists.
 */
class SaedEstSubcontractorDeductionCorrectionSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('vat_number', '314526094900003')->first();

        if (! $company) {
            $this->command?->warn('Dynamic Core Contracting Company not found — run RealCompanySeeder first.');

            return;
        }

        $saedBill = Bill::where('company_id', $company->id)
            ->whereHas('supplier', fn ($q) => $q->where('cr_number', '4031259996'))
            ->first();

        if (! $saedBill) {
            $this->command?->error('The SAED EST bill was not found — run SaedEstSubcontractorBillSeeder first.');

            return;
        }

        if (SubcontractorDeduction::where('company_id', $company->id)->where('bill_id', $saedBill->id)->exists()) {
            $this->command?->info('Subcontractor deduction already recorded — skipping.');

            return;
        }

        $purchaseReturn = PurchaseReturn::where('company_id', $company->id)->where('bill_id', $saedBill->id)->first();

        if (! $purchaseReturn) {
            $this->command?->error('No Purchase Return found against the SAED EST bill to correct — run SaedEstMaterialSupplierCorrectionSeeder first.');

            return;
        }

        if ($purchaseReturn->status === 'void') {
            $this->command?->error('The original Purchase Return is already void but no Subcontractor Deduction exists — check manually before re-running.');

            return;
        }

        $ledger = app(LedgerPostingService::class);

        DB::transaction(function () use ($company, $saedBill, $purchaseReturn, $ledger) {
            $ledger->reverse($company, 'purchase_return', $purchaseReturn->id, "Purchase Return {$purchaseReturn->return_number} voided — reclassified as a Subcontractor Deduction (not a real purchase return)");
            $purchaseReturn->update(['status' => 'void']);

            $deduction = SubcontractorDeduction::create([
                'company_id' => $company->id,
                'bill_id' => $saedBill->id,
                'supplier_id' => $saedBill->supplier_id,
                'branch_id' => $saedBill->branch_id,
                'deduction_number' => $company->nextSubcontractorDeductionNumber(),
                'issue_date' => $purchaseReturn->issue_date,
                'reason' => $purchaseReturn->reason,
                'status' => 'issued',
                'amount' => $purchaseReturn->total,
                'currency' => $purchaseReturn->currency,
                'exchange_rate' => $purchaseReturn->exchange_rate,
            ]);

            $ledger->postSubcontractorDeduction($deduction);

            $this->command?->info("Subcontractor deduction {$deduction->deduction_number} recorded — SAR ".number_format((float) $deduction->amount, 2).' credited against '.$saedBill->bill_number.' (replacing voided '.$purchaseReturn->return_number.').');
        });
    }
}
