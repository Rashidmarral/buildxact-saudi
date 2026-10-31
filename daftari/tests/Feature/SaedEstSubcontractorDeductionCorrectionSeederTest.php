<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PurchaseReturn;
use App\Models\SubcontractorDeduction;
use Database\Seeders\SaedEstMaterialSupplierCorrectionSeeder;
use Database\Seeders\SaedEstSubcontractorBillSeeder;
use Database\Seeders\SaedEstSubcontractorDeductionCorrectionSeeder;
use Database\Seeders\SaedEstSubcontractorPaymentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "purchase return we have done in a wrong sense please fix all" —
 * PR-00001 against the SAED EST bill was never a real Purchase
 * Return (nothing was ever bought from Shahid to return to him). This
 * voids it and records the same SAR 134,395.68 as a proper
 * Subcontractor Deduction instead, with identical GL effect.
 */
class SaedEstSubcontractorDeductionCorrectionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedUpToPurchaseReturn(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        $project = \App\Models\Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = \App\Models\Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = \App\Models\Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'title' => 'Subbase, Base Course and Asphalt work', 'contract_value' => 373650.00,
            'retention_percent' => 5, 'status' => 'active', 'start_date' => '2026-09-25',
        ]);
        foreach ([
            ['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730.00],
            ['sequence' => 2, 'name' => 'Milestone 2', 'percentage' => 30, 'amount' => 112095.00],
            ['sequence' => 3, 'name' => 'Milestone 3', 'percentage' => 30, 'amount' => 112095.00],
            ['sequence' => 4, 'name' => 'Milestone 4', 'percentage' => 15, 'amount' => 56047.50],
            ['sequence' => 5, 'name' => 'Final', 'percentage' => 5, 'amount' => 18682.50, 'requires_handover_approval' => true],
        ] as $m) {
            $subcontract->milestones()->create($m);
        }

        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new SaedEstSubcontractorBillSeeder)->run();
        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $saedBill = Bill::where('company_id', $company->id)->where('supplier_id', $supplier->id)->first();

        return [$company, $saedBill];
    }

    public function test_it_errors_cleanly_without_a_purchase_return_to_correct(): void
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);

        (new SaedEstSubcontractorDeductionCorrectionSeeder)->run();

        $this->assertSame(0, SubcontractorDeduction::count());
    }

    public function test_it_voids_the_purchase_return_and_creates_an_equivalent_deduction(): void
    {
        [$company, $saedBill] = $this->seedUpToPurchaseReturn();
        $purchaseReturn = PurchaseReturn::where('bill_id', $saedBill->id)->first();
        $this->assertNotNull($purchaseReturn);
        $this->assertSame('issued', $purchaseReturn->status);
        $originalTotal = (float) $purchaseReturn->total;

        (new SaedEstSubcontractorDeductionCorrectionSeeder)->run();

        $this->assertSame('void', $purchaseReturn->fresh()->status);

        $deduction = SubcontractorDeduction::where('company_id', $company->id)->where('bill_id', $saedBill->id)->first();
        $this->assertNotNull($deduction);
        $this->assertEqualsWithDelta($originalTotal, (float) $deduction->amount, 0.01);
        $this->assertEqualsWithDelta(134395.68, (float) $deduction->amount, 0.01);
        $this->assertSame('issued', $deduction->status);
    }

    /**
     * The whole point: Shahid's bill ends up owing exactly what it did
     * before, just correctly labeled.
     */
    public function test_the_net_gl_effect_on_the_saed_est_bill_is_unchanged(): void
    {
        [$company, $saedBill] = $this->seedUpToPurchaseReturn();

        $ap = Account::where('company_id', $company->id)->where('code', '2000')->first();
        $expense = Account::where('company_id', $company->id)->where('code', '5100')->first();

        $netApBefore = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
            ->where('account_id', $ap->id)->selectRaw('COALESCE(SUM(credit),0)-COALESCE(SUM(debit),0) as net')->value('net');
        $netExpenseBefore = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
            ->where('account_id', $expense->id)->selectRaw('COALESCE(SUM(debit),0)-COALESCE(SUM(credit),0) as net')->value('net');

        (new SaedEstSubcontractorDeductionCorrectionSeeder)->run();

        $netApAfter = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
            ->where('account_id', $ap->id)->selectRaw('COALESCE(SUM(credit),0)-COALESCE(SUM(debit),0) as net')->value('net');
        $netExpenseAfter = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
            ->where('account_id', $expense->id)->selectRaw('COALESCE(SUM(debit),0)-COALESCE(SUM(credit),0) as net')->value('net');

        $this->assertEqualsWithDelta($netApBefore, $netApAfter, 0.01);
        $this->assertEqualsWithDelta($netExpenseBefore, $netExpenseAfter, 0.01);
    }

    public function test_reseeding_does_not_duplicate_anything(): void
    {
        $this->seedUpToPurchaseReturn();

        (new SaedEstSubcontractorDeductionCorrectionSeeder)->run();
        (new SaedEstSubcontractorDeductionCorrectionSeeder)->run();

        $this->assertSame(1, SubcontractorDeduction::count());
        $this->assertSame(1, JournalEntry::where('source_type', 'subcontractor_deduction')->count());
        $this->assertSame(1, JournalEntry::where('source_type', 'purchase_return_reversal')->count());
    }
}
