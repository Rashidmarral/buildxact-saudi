<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use Database\Seeders\ShahidUntrackedVouchersLinkageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Master Report, Section 11 #10: PV-00036/37/38/39 (SAR 18,560.00 total)
 * are real, already-posted vouchers against SAED EST's bill — this only
 * adds the missing SubcontractPayment rows, never a voucher or a journal
 * entry.
 */
class ShahidUntrackedVouchersLinkageSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(bool $withVouchers = true): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        $operating = BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'is_active' => true]);

        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'title' => 'Subbase, Base Course and Asphalt work', 'contract_value' => 373650.00,
            'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Advance', 'percentage' => 100, 'amount' => 373650.00]);

        if ($withVouchers) {
            foreach ([
                ['voucher_number' => 'PV-00036', 'date' => '2026-10-28', 'amount' => 10000.00, 'method' => 'bank_transfer', 'reference' => 'Transfer to Shahid sir account 4000 and 6000'],
                ['voucher_number' => 'PV-00037', 'date' => '2026-10-29', 'amount' => 6000.00, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Sir for RC2 Work'],
                ['voucher_number' => 'PV-00038', 'date' => '2026-10-24', 'amount' => 1760.00, 'method' => 'bank_transfer', 'reference' => 'Payment to Shahid Remaining due of Ashraf'],
                ['voucher_number' => 'PV-00039', 'date' => '2026-10-24', 'amount' => 800.00, 'method' => 'cash', 'reference' => 'Payment to Shahid in terms of Mubashir and Khalid sir'],
            ] as $row) {
                PaymentVoucher::create([
                    'company_id' => $company->id, 'bank_account_id' => $operating->id, 'party_type' => 'supplier',
                    'supplier_id' => $supplier->id, 'voucher_number' => $row['voucher_number'], 'date' => $row['date'],
                    'payee_name' => 'SAED EST', 'amount' => $row['amount'], 'method' => $row['method'],
                    'reference' => $row['reference'], 'status' => 'issued',
                ]);
            }
        }

        return [$company, $subcontract];
    }

    public function test_it_errors_cleanly_when_the_expected_vouchers_are_missing(): void
    {
        $this->seedFixture(withVouchers: false);

        (new ShahidUntrackedVouchersLinkageSeeder)->run();

        $this->assertSame(0, SubcontractPayment::count());
    }

    public function test_it_links_all_four_vouchers_without_touching_the_gl(): void
    {
        [$company, $subcontract] = $this->seedFixture();
        $journalCountBefore = JournalEntry::count();

        (new ShahidUntrackedVouchersLinkageSeeder)->run();

        $this->assertSame(4, SubcontractPayment::count());
        $this->assertEqualsWithDelta(18560.00, $subcontract->fresh()->totalPaid(), 0.01);
        $this->assertSame($journalCountBefore, JournalEntry::count(), 'this seeder must never post a journal entry — the vouchers are already posted');

        foreach (['PV-00036', 'PV-00037', 'PV-00038', 'PV-00039'] as $voucherNumber) {
            $voucher = PaymentVoucher::where('company_id', $company->id)->where('voucher_number', $voucherNumber)->first();
            $this->assertTrue(SubcontractPayment::where('payment_voucher_id', $voucher->id)->exists(), "{$voucherNumber} should be linked");
        }
    }

    public function test_reseeding_does_not_duplicate_the_links(): void
    {
        $this->seedFixture();

        (new ShahidUntrackedVouchersLinkageSeeder)->run();
        (new ShahidUntrackedVouchersLinkageSeeder)->run();

        $this->assertSame(4, SubcontractPayment::count());
    }
}
