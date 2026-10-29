<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\PurchaseReturn;
use App\Models\Subcontract;
use App\Models\Supplier;
use Database\Seeders\SaedEstMaterialSupplierCorrectionSeeder;
use Database\Seeders\SaedEstSubcontractorBillSeeder;
use Database\Seeders\SaedEstSubcontractorPaymentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "our main supplier or subcontractor is Shahid Saed and we have agreed
 * prices with him without vat so what we have do we have purchased base
 * course from aljazera with vat and provide it to shahid... the same we
 * have do for asphalt like dar al manajin give us asphalt with vat 15%"
 * — Al Jazeera and Dar Al Manajim are real, separate, VAT-registered
 * suppliers, not part of the Shahid subcontract. This seeder gives each
 * its own real 15%-VAT bill and credits the net cost against SAED EST's
 * bill via a Purchase Return, so the material is expensed exactly once
 * and its input VAT is finally captured. Dar Al Manajim's own real tax
 * invoice (CRED-2568) and SOA confirmed the exact figures, including a
 * SAR 789.43 balance that was still open after the first 3 vouchers.
 */
class SaedEstMaterialSupplierCorrectionSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function seedFullSubcontract(Company $company): Subcontract
    {
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = Subcontract::create([
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

        return $subcontract;
    }

    private function seedUpToBill(): Company
    {
        $company = $this->seedCompany();
        $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new SaedEstSubcontractorBillSeeder)->run();

        return $company;
    }

    public function test_it_errors_cleanly_when_the_saed_est_bill_does_not_exist_yet(): void
    {
        $this->seedCompany();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $this->assertSame(0, Supplier::count());
        $this->assertSame(0, PurchaseReturn::count());
    }

    public function test_it_creates_two_real_vat_registered_bills_for_the_material_suppliers(): void
    {
        $company = $this->seedUpToBill();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $alJazeera = Supplier::where('company_id', $company->id)->where('name', 'SHAERIKAIH ALGAZAIERIH ALERIBAIH')->first();
        $darAlManajim = Supplier::where('company_id', $company->id)->where('name', 'DAR AL MANAJIM COMPANY FOR MINING & INDUSTRY LTD')->first();
        $this->assertNotNull($alJazeera);
        $this->assertNotNull($darAlManajim);
        $this->assertSame('300075266900003', $darAlManajim->vat_number);
        $this->assertSame('1010228923', $darAlManajim->cr_number);

        $alJazeeraBill = Bill::where('supplier_id', $alJazeera->id)->first();
        $darAlManajimBill = Bill::where('supplier_id', $darAlManajim->id)->first();

        $this->assertSame('posted', $alJazeeraBill->status);
        $this->assertEqualsWithDelta(27043.48, (float) $alJazeeraBill->subtotal, 0.01);
        $this->assertEqualsWithDelta(4056.52, (float) $alJazeeraBill->vat_total, 0.01);
        $this->assertEqualsWithDelta(31100.00, (float) $alJazeeraBill->total, 0.01);
        $this->assertEqualsWithDelta(31100.00, (float) $alJazeeraBill->amount_paid, 0.01, 'fully settled by the real vouchers, zero balance');
        $this->assertEqualsWithDelta(0.00, $alJazeeraBill->balanceDue(), 0.01);

        // Real figures straight off Dar Al Manajim's own tax invoice CRED-2568.
        $this->assertSame('CRED-2568', $darAlManajimBill->supplier_reference);
        $this->assertEqualsWithDelta(107352.20, (float) $darAlManajimBill->subtotal, 0.01);
        $this->assertEqualsWithDelta(16102.83, (float) $darAlManajimBill->vat_total, 0.01);
        $this->assertEqualsWithDelta(123455.03, (float) $darAlManajimBill->total, 0.01);
        // Fully settled: the 3 original vouchers (SAR 122,665.60) plus the
        // SAR 789.43 remaining-balance voucher this seeder also records.
        $this->assertEqualsWithDelta(123455.03, (float) $darAlManajimBill->amount_paid, 0.01);
        $this->assertEqualsWithDelta(0.00, $darAlManajimBill->balanceDue(), 0.01);

        // SAR 20,159.35 of real input VAT that was never captured before.
        $this->assertEqualsWithDelta(20159.35, (float) $alJazeeraBill->vat_total + (float) $darAlManajimBill->vat_total, 0.01);
    }

    public function test_the_eight_vouchers_move_off_the_saed_est_bill_onto_the_real_supplier_bills(): void
    {
        $company = $this->seedUpToBill();
        $saedBill = Bill::whereHas('supplier', fn ($q) => $q->where('cr_number', '4031259996'))->first();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $alJazeeraVouchers = PaymentVoucher::whereIn('voucher_number', ['PV-00013', 'PV-00016', 'PV-00019', 'PV-00021', 'PV-00022'])->get();
        $manajimVouchers = PaymentVoucher::whereIn('voucher_number', ['PV-00029', 'PV-00032', 'PV-00033'])->get();

        foreach ($alJazeeraVouchers->merge($manajimVouchers) as $voucher) {
            $this->assertNotEquals($saedBill->id, $voucher->bill_id, "{$voucher->voucher_number} should no longer settle the SAED EST bill");
            $this->assertNotNull($voucher->bill_payment_id);
            // Each voucher now settles its own real bill at its full real (gross) amount.
            $this->assertEqualsWithDelta((float) $voucher->amount, (float) $voucher->billPayment->amount, 0.01);
        }

        $this->assertSame('SHAERIKAIH ALGAZAIERIH ALERIBAIH', $alJazeeraVouchers->first()->payee_name);
        $this->assertSame('DAR AL MANAJIM COMPANY FOR MINING & INDUSTRY LTD', $manajimVouchers->first()->payee_name);

        // 28 real vouchers total, minus the 8 reassigned here.
        $this->assertSame(20, PaymentVoucher::where('bill_id', $saedBill->id)->count());
    }

    /**
     * "remaining 789 due is also paid" — Dar Al Manajim's own SOA shows
     * SAR 789.43 still open after the first 3 vouchers (SAR 122,665.60
     * against the real SAR 123,455.03 invoice total). This is a brand
     * new real cash payment, not among the original 28 vouchers.
     */
    public function test_the_remaining_balance_is_recorded_as_a_new_real_voucher(): void
    {
        $company = $this->seedUpToBill();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $darAlManajim = Supplier::where('company_id', $company->id)->where('name', 'DAR AL MANAJIM COMPANY FOR MINING & INDUSTRY LTD')->first();
        $voucher = PaymentVoucher::where('supplier_id', $darAlManajim->id)
            ->where('reference', 'CRED-2568 remaining balance settled')
            ->first();

        $this->assertNotNull($voucher);
        $this->assertEqualsWithDelta(789.43, (float) $voucher->amount, 0.01);
        $this->assertSame('2026-10-29', $voucher->date->format('Y-m-d'));
        $this->assertNotNull($voucher->bill_payment_id);
        $this->assertEqualsWithDelta(789.43, (float) $voucher->billPayment->amount, 0.01);

        // It does not change what's credited to Shahid — that stays
        // fixed at the invoice's own net cost either way.
        $this->assertSame(4, PaymentVoucher::where('supplier_id', $darAlManajim->id)->count());
    }

    public function test_reseeding_does_not_duplicate_the_remaining_balance_voucher(): void
    {
        $this->seedUpToBill();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();
        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $this->assertSame(1, PaymentVoucher::where('reference', 'CRED-2568 remaining balance settled')->count());
    }

    public function test_a_purchase_return_credits_the_saed_est_bill_for_the_combined_net_material_cost(): void
    {
        $this->seedUpToBill();
        $saedBill = Bill::whereHas('supplier', fn ($q) => $q->where('cr_number', '4031259996'))->first();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $purchaseReturn = PurchaseReturn::where('bill_id', $saedBill->id)->first();
        $this->assertNotNull($purchaseReturn);
        // SAR 27,043.48 (Al Jazeera) + SAR 107,352.20 (Dar Al Manajim, the
        // real invoice's own net figure) = SAR 134,395.68.
        $this->assertEqualsWithDelta(134395.68, (float) $purchaseReturn->total, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $purchaseReturn->vat_total, 0.01, 'SAED EST itself carries no VAT');
        $this->assertSame(1, JournalEntry::where('source_type', 'purchase_return')->count());
    }

    /**
     * The real accounting payoff: before this seeder, the material cost
     * was expensed once through the SAED EST accrual and its input VAT
     * was never claimed. After, it's expensed exactly once in total
     * (reversed on SAED EST, re-recognized on the two real bills) and
     * the VAT is now a real, recoverable input VAT line.
     */
    public function test_total_operating_expense_recognized_is_still_exactly_the_contract_value(): void
    {
        $company = $this->seedUpToBill();

        $expenseAccount = AccountMapping::resolve($company->id, 'DEFAULT_OPERATING_EXPENSES');
        $vatInputAccount = AccountMapping::resolve($company->id, 'VAT_INPUT');

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        if ($expenseAccount) {
            $netExpense = JournalEntryLine::where('account_id', $expenseAccount->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id))
                ->selectRaw('COALESCE(SUM(debit),0) - COALESCE(SUM(credit),0) as net')
                ->value('net');

            $this->assertEqualsWithDelta(373650.00, (float) $netExpense, 0.01);
        }

        if ($vatInputAccount) {
            $vatInputRecognized = JournalEntryLine::where('account_id', $vatInputAccount->id)
                ->whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'bill'))
                ->sum('debit');

            $this->assertGreaterThanOrEqual(20159.00, (float) $vatInputRecognized);
        }
    }

    /**
     * Every real voucher that already existed before this seeder runs
     * still moves exactly the same real cash — reassigning who it
     * settles never touches its own bank/cash line. The one genuinely
     * NEW cash line (the SAR 789.43 remaining-balance payment) is the
     * sole, expected addition.
     */
    public function test_bank_and_cash_side_journal_lines_only_gain_the_new_remaining_balance_payment(): void
    {
        $company = $this->seedUpToBill();

        $before = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->get(['account_id', 'debit', 'credit'])
            ->map(fn ($l) => round((float) $l->debit, 2).'|'.round((float) $l->credit, 2).'|'.$l->account_id)
            ->sort()->values();
        $totalBefore = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $after = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->get(['account_id', 'debit', 'credit'])
            ->map(fn ($l) => round((float) $l->debit, 2).'|'.round((float) $l->credit, 2).'|'.$l->account_id)
            ->sort()->values();
        $totalAfter = (float) JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        // Every line that existed before still exists afterward, unchanged.
        foreach ($before as $line) {
            $this->assertContains($line, $after->toArray());
        }

        $this->assertEqualsWithDelta(789.43, $totalAfter - $totalBefore, 0.01);
    }

    public function test_reseeding_does_not_duplicate_anything(): void
    {
        $this->seedUpToBill();

        (new SaedEstMaterialSupplierCorrectionSeeder)->run();
        (new SaedEstMaterialSupplierCorrectionSeeder)->run();

        $this->assertSame(2, Supplier::whereIn('name', ['SHAERIKAIH ALGAZAIERIH ALERIBAIH', 'DAR AL MANAJIM COMPANY FOR MINING & INDUSTRY LTD'])->count());
        $this->assertSame(2, Bill::whereHas('supplier', fn ($q) => $q->whereIn('name', ['SHAERIKAIH ALGAZAIERIH ALERIBAIH', 'DAR AL MANAJIM COMPANY FOR MINING & INDUSTRY LTD']))->count());
        $this->assertSame(1, PurchaseReturn::count());
        $this->assertSame(1, JournalEntry::where('source_type', 'purchase_return')->count());
    }
}
