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
use Database\Seeders\ShahidTransferOct29Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add more 10000 to shahid account trasfer today" — one new real
 * SAR 10,000 bank transfer, posted through the normal voucher/GL path
 * and linked into the already-seeded SAED EST subcontract.
 */
class ShahidTransferOct29SeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompanyProjectSupplierSubcontract(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);
        BankAccount::create(['company_id' => $company->id, 'name' => 'SNB — Operating Account', 'type' => 'bank', 'is_active' => true]);

        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ_00001', 'name' => 'مشروع ساحة الجموم', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST', 'cr_number' => '4031259996']);
        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'title' => 'Subbase, Base Course and Asphalt work', 'contract_value' => 373650.00,
            'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Advance', 'percentage' => 100, 'amount' => 373650.00]);

        return [$company, $subcontract];
    }

    public function test_it_errors_cleanly_when_the_subcontract_does_not_exist_yet(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new ShahidTransferOct29Seeder)->run();

        $this->assertSame(0, PaymentVoucher::count());
    }

    public function test_it_creates_and_posts_the_transfer_and_links_it_into_the_subcontract(): void
    {
        [$company, $subcontract] = $this->seedCompanyProjectSupplierSubcontract();

        (new ShahidTransferOct29Seeder)->run();

        $voucher = PaymentVoucher::where('company_id', $company->id)->first();
        $this->assertNotNull($voucher);
        $this->assertEqualsWithDelta(10000.00, (float) $voucher->amount, 0.01);
        $this->assertSame('bank_transfer', $voucher->method);
        $this->assertSame('issued', $voucher->status);
        $this->assertSame(now()->toDateString(), $voucher->date->toDateString());

        $this->assertSame(1, JournalEntry::where('company_id', $company->id)->where('source_type', 'payment_voucher')->count());
        $this->assertEqualsWithDelta(10000.00, $subcontract->fresh()->totalPaid(), 0.01);
        $this->assertSame(1, SubcontractPayment::where('payment_voucher_id', $voucher->id)->count());
    }

    public function test_reseeding_does_not_duplicate_the_transfer(): void
    {
        $this->seedCompanyProjectSupplierSubcontract();

        (new ShahidTransferOct29Seeder)->run();
        (new ShahidTransferOct29Seeder)->run();

        $this->assertSame(1, PaymentVoucher::count());
        $this->assertSame(1, JournalEntry::where('source_type', 'payment_voucher')->count());
        $this->assertSame(1, SubcontractPayment::count());
    }

    public function test_it_skips_when_the_transfer_was_already_entered_through_the_ui(): void
    {
        [$company, $subcontract] = $this->seedCompanyProjectSupplierSubcontract();
        $operating = BankAccount::where('company_id', $company->id)->first();
        $supplier = Supplier::where('company_id', $company->id)->first();

        PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $operating->id, 'party_type' => 'supplier',
            'supplier_id' => $supplier->id, 'voucher_number' => 'PV-00099', 'date' => now()->toDateString(),
            'payee_name' => 'SAED EST', 'amount' => 10000.00, 'method' => 'bank_transfer',
            'reference' => 'Manually entered Shahid transfer', 'status' => 'issued',
        ]);

        (new ShahidTransferOct29Seeder)->run();

        $this->assertSame(1, PaymentVoucher::count(), 'should not create a second voucher once a matching one already exists');
    }
}
