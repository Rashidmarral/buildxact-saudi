<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use Database\Seeders\OwnerPersonalExpenseReclassificationSeeder;
use Database\Seeders\SaedEstSubcontractorPaymentsSeeder;
use Database\Seeders\ZubaidiCashReconciliationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "the 55k cash i will explain we have transfer the 25k first to
 * zubaidi account and this 25k used 19k payment voucher issued like
 * 15k and 4k to shahid remaining 6k 1k add to owner remaining 5k then
 * transfer 30k to zubaidi... now 10k issue payment voucher for shahid
 * add more 25k to owner" — the real 15k and 10k Shahid vouchers
 * already exist (seeded by SaedEstSubcontractorPaymentsSeeder); this
 * fills the real gaps: SAR 4,000 to Shahid and SAR 26,000 of owner
 * drawings.
 */
class ZubaidiCashReconciliationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyWithRealSubcontract(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

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

        return [$company, $subcontract];
    }

    private function seedUpTo(): array
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();
        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new OwnerPersonalExpenseReclassificationSeeder)->run();

        return [$company, $subcontract];
    }

    public function test_it_creates_the_missing_4000_shahid_voucher_linked_into_the_subcontract(): void
    {
        [$company, $subcontract] = $this->seedUpTo();
        $totalPaidBefore = $subcontract->fresh()->totalPaid();

        (new ZubaidiCashReconciliationSeeder)->run();

        // amount=4000 alone isn't unique — the real backfill already has
        // an unrelated SAR 4,000 voucher (PV-00028, "in terms of MC1").
        $voucher = PaymentVoucher::where('company_id', $company->id)->where('amount', 4000.00)->where('reference', 'like', '%Zubaidi cash%')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('cash', $voucher->method);
        $this->assertSame('2026-10-12', $voucher->date->format('Y-m-d'));
        $this->assertSame(2, PaymentVoucher::where('company_id', $company->id)->where('amount', 4000.00)->count(), 'the pre-existing unrelated MC1 voucher must still be there too');
        $this->assertEqualsWithDelta($totalPaidBefore + 4000.00, $subcontract->fresh()->totalPaid(), 0.01);
    }

    public function test_it_creates_both_owner_drawing_vouchers_posted_to_the_drawings_account(): void
    {
        [$company] = $this->seedUpTo();
        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();

        (new ZubaidiCashReconciliationSeeder)->run();

        $draw1 = PaymentVoucher::where('company_id', $company->id)->where('amount', 1000.00)->where('party_type', 'manual')->first();
        $draw25 = PaymentVoucher::where('company_id', $company->id)->where('amount', 25000.00)->where('party_type', 'manual')->first();

        $this->assertNotNull($draw1);
        $this->assertNotNull($draw25);
        $this->assertSame($drawings->id, $draw1->counter_account_id);
        $this->assertSame($drawings->id, $draw25->counter_account_id);

        foreach ([$draw1, $draw25] as $voucher) {
            $je = JournalEntry::where('source_type', 'payment_voucher')->where('source_id', $voucher->id)->first();
            $this->assertNotNull($je);
            $line = JournalEntryLine::where('journal_entry_id', $je->id)->where('account_id', $drawings->id)->first();
            $this->assertNotNull($line);
            $this->assertEqualsWithDelta((float) $voucher->amount, (float) $line->debit, 0.01);
        }
    }

    /**
     * The real accounting point of this whole exercise: none of this
     * SAR 30,000 (4k Shahid + 26k drawings) ever touches Operating
     * Expenses, Accounts Payable's SAED EST balance beyond the real 4k,
     * or any already-posted real voucher — it is three new, independent
     * entries.
     */
    public function test_bank_and_cash_side_journal_lines_only_gain_these_three_new_vouchers(): void
    {
        [$company] = $this->seedUpTo();

        $before = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new ZubaidiCashReconciliationSeeder)->run();

        $after = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('company_id', $company->id)->where('source_type', 'payment_voucher'))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        $this->assertEqualsWithDelta(30000.00, (float) $after - (float) $before, 0.01);
    }

    public function test_reseeding_does_not_duplicate_anything(): void
    {
        $this->seedUpTo();

        (new ZubaidiCashReconciliationSeeder)->run();
        (new ZubaidiCashReconciliationSeeder)->run();

        $this->assertSame(1, PaymentVoucher::where('amount', 4000.00)->where('reference', 'like', '%Zubaidi cash%')->count());
        $this->assertSame(1, PaymentVoucher::where('amount', 1000.00)->where('party_type', 'manual')->count());
        $this->assertSame(1, PaymentVoucher::where('amount', 25000.00)->where('party_type', 'manual')->count());
    }
}
