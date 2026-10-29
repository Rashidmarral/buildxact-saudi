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
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SaedEstSubcontractorBillSeeder;
use Database\Seeders\SaedEstSubcontractorPaymentsSeeder;
use Database\Seeders\ShahidTransferOct29Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "supplier bill create bill against subcontractor and assign payment
 * voucher against those bill to generate account statemt of
 * supplier/subcontractor as well" — every real SAED EST voucher was
 * posted as a direct operating expense with no bill_id, so the
 * Supplier Account Statement (Bill + BillPayment only) showed nothing
 * for this subcontractor. Creates the real accrual bill and reassigns
 * every real voucher to settle it.
 */
class SaedEstSubcontractorBillSeederTest extends TestCase
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

    public function test_it_errors_cleanly_when_the_subcontract_does_not_exist_yet(): void
    {
        $this->seedCompany();

        (new SaedEstSubcontractorBillSeeder)->run();

        $this->assertSame(0, Bill::count());
    }

    public function test_it_creates_and_posts_a_bill_matching_the_contract_value(): void
    {
        $company = $this->seedCompany();
        $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();

        (new SaedEstSubcontractorBillSeeder)->run();

        $bill = Bill::where('company_id', $company->id)->first();
        $this->assertNotNull($bill);
        $this->assertSame('posted', $bill->status);
        $this->assertEqualsWithDelta(373650.00, (float) $bill->total, 0.01);
        $this->assertEqualsWithDelta(0.00, (float) $bill->vat_total, 0.01);
        $this->assertCount(4, $bill->items);

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'bill')->count());
    }

    public function test_every_real_voucher_is_reassigned_to_settle_the_bill(): void
    {
        $company = $this->seedCompany();
        $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new ShahidTransferOct29Seeder)->run();

        (new SaedEstSubcontractorBillSeeder)->run();

        $bill = Bill::where('company_id', $company->id)->first();
        $vouchers = PaymentVoucher::where('company_id', $company->id)->where('supplier_id', $bill->supplier_id)->get();

        $this->assertCount(29, $vouchers);
        $this->assertTrue($vouchers->every(fn (PaymentVoucher $v) => $v->bill_id === $bill->id && $v->bill_payment_id !== null));

        $this->assertEqualsWithDelta(362065.60, (float) $bill->fresh()->amount_paid, 0.01);
        $this->assertEqualsWithDelta(373650.00 - 362065.60, $bill->fresh()->balanceDue(), 0.01);
    }

    /**
     * The single most important invariant: reclassifying these vouchers
     * from a direct expense to a bill settlement must never move a
     * single riyal of real cash. Every bank/cash-side journal line must
     * be byte-for-byte the same amount, on the same account, before and
     * after — only the counterpart (expense vs. accounts payable) changes.
     */
    public function test_bank_and_cash_side_journal_lines_are_completely_unchanged(): void
    {
        $company = $this->seedCompany();
        $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new ShahidTransferOct29Seeder)->run();

        $cashBankLinesBefore = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->get(['account_id', 'debit', 'credit'])
            ->map(fn ($l) => round((float) $l->debit, 2).'|'.round((float) $l->credit, 2).'|'.$l->account_id)
            ->sort()->values();
        $totalBefore = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new SaedEstSubcontractorBillSeeder)->run();

        $cashBankLinesAfter = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->get(['account_id', 'debit', 'credit'])
            ->map(fn ($l) => round((float) $l->debit, 2).'|'.round((float) $l->credit, 2).'|'.$l->account_id)
            ->sort()->values();
        $totalAfter = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        $this->assertEqualsWithDelta((float) $totalBefore, (float) $totalAfter, 0.01);
        $this->assertSame($cashBankLinesBefore->toArray(), $cashBankLinesAfter->toArray());
    }

    public function test_the_supplier_account_statement_now_shows_the_bill_and_payments(): void
    {
        $company = $this->seedCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $subcontract = $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();

        // Before: the statement is empty for this supplier (no bill exists yet).
        $before = $this->actingAs($owner)->get(route('app.reports.account-statement', ['type' => 'supplier', 'supplier_id' => $subcontract->supplier_id]));
        $before->assertOk();
        $before->assertSee(__('No activity for this customer in the selected period.'));

        (new SaedEstSubcontractorBillSeeder)->run();

        $bill = Bill::where('company_id', $company->id)->first();
        $after = $this->actingAs($owner)->get(route('app.reports.account-statement', [
            'type' => 'supplier', 'supplier_id' => $subcontract->supplier_id, 'period' => 'custom',
            'from' => '2026-09-01', 'to' => '2026-11-30',
        ]));

        $after->assertOk();
        $after->assertSee($bill->bill_number);
    }

    public function test_reseeding_does_not_duplicate_the_bill_or_reassign_vouchers_twice(): void
    {
        $company = $this->seedCompany();
        $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();

        (new SaedEstSubcontractorBillSeeder)->run();
        (new SaedEstSubcontractorBillSeeder)->run();

        $this->assertSame(1, Bill::count());
        $this->assertSame(1, JournalEntry::where('source_type', 'bill')->count());
        $this->assertSame(28, JournalEntry::where('source_type', 'payment_voucher')->count());
    }

    public function test_a_later_voucher_added_after_the_first_run_gets_picked_up_on_a_second_run(): void
    {
        $company = $this->seedCompany();
        $this->seedFullSubcontract($company);
        (new SaedEstSubcontractorPaymentsSeeder)->run();

        (new SaedEstSubcontractorBillSeeder)->run();
        (new ShahidTransferOct29Seeder)->run();
        (new SaedEstSubcontractorBillSeeder)->run();

        $bill = Bill::where('company_id', $company->id)->first();
        $newVoucher = PaymentVoucher::where('company_id', $company->id)->latest('id')->first();

        $this->assertSame($bill->id, $newVoucher->bill_id);
        $this->assertEqualsWithDelta(362065.60, (float) $bill->fresh()->amount_paid, 0.01);
    }
}
