<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Company;
use App\Models\JournalEntry;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\SaedEstSubcontractorPaymentsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "can you please give me seeder to add these payments against
 * subcontractor" — the 28-voucher "SAED EST" Payment Summary PDF, now
 * backfilled through the real voucher/GL-posting path (each voucher is
 * firstOrCreate'd then posted through LedgerPostingService, exactly
 * like PaymentVoucherController::store() would) and linked into one
 * Subcontract per real sub-party, with the two VAT corrections the
 * operator confirmed.
 */
class SaedEstSubcontractorPaymentsSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    public function test_it_creates_every_voucher_posts_it_and_links_it_into_the_right_subcontract(): void
    {
        $company = $this->seedCompany();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $this->assertSame(26, PaymentVoucher::where('company_id', $company->id)->count());
        $this->assertSame(26, JournalEntry::where('company_id', $company->id)->where('source_type', 'payment_voucher')->count());
        $this->assertSame(26, SubcontractPayment::where('company_id', $company->id)->count());

        $supplier = Supplier::where('company_id', $company->id)->where('name', 'SAED EST')->first();
        $this->assertNotNull($supplier);
        $this->assertSame('4031259996', $supplier->cr_number);

        $project = Project::where('company_id', $company->id)->where('name', 'Jumum Yard Project')->first();
        $this->assertNotNull($project);

        $this->assertSame(4, Subcontract::where('company_id', $company->id)->count());
    }

    public function test_the_asphalt_group_applies_the_confirmed_net_of_vat_total_while_keeping_the_real_voucher_amounts(): void
    {
        $this->seedCompany();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $subcontract = Subcontract::where('title', 'Asphalt (Dar Al Anajim)')->first();
        $this->assertNotNull($subcontract);

        $this->assertEqualsWithDelta(122665.60, $subcontract->totalReceivedGross(), 0.01);
        $this->assertEqualsWithDelta(107352.20, $subcontract->totalPaid(), 0.01);

        // Every real voucher still carries its own true (VAT-inclusive) amount.
        $this->assertEqualsWithDelta(82965.60, PaymentVoucher::where('voucher_number', 'PV-00029')->first()->amount, 0.01);
        $this->assertEqualsWithDelta(19800.00, PaymentVoucher::where('voucher_number', 'PV-00032')->first()->amount, 0.01);
        $this->assertEqualsWithDelta(19900.00, PaymentVoucher::where('voucher_number', 'PV-00033')->first()->amount, 0.01);
    }

    public function test_the_al_jazeera_group_applies_the_vat_inclusive_unwind_per_voucher(): void
    {
        $this->seedCompany();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $subcontract = Subcontract::where('title', 'Al Jazeera')->first();
        $this->assertNotNull($subcontract);

        $this->assertEqualsWithDelta(31100.00, $subcontract->totalReceivedGross(), 0.01);
        $this->assertEqualsWithDelta(31100.00 / 1.15, $subcontract->totalPaid(), 0.01);
    }

    public function test_shahid_and_ashraf_have_no_vat_adjustment_net_equals_gross(): void
    {
        $this->seedCompany();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $shahid = Subcontract::where('title', 'Shahid')->first();
        $ashraf = Subcontract::where('title', 'Ashraf')->first();

        $this->assertEqualsWithDelta(114800.00, $shahid->totalPaid(), 0.01);
        $this->assertEqualsWithDelta(114800.00, $shahid->totalReceivedGross(), 0.01);
        $this->assertEqualsWithDelta(61700.00, $ashraf->totalPaid(), 0.01);
        $this->assertEqualsWithDelta(61700.00, $ashraf->totalReceivedGross(), 0.01);
    }

    public function test_reseeding_does_not_duplicate_vouchers_payments_or_subcontracts(): void
    {
        $this->seedCompany();

        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $this->assertSame(26, PaymentVoucher::count());
        $this->assertSame(26, JournalEntry::where('source_type', 'payment_voucher')->count());
        $this->assertSame(26, SubcontractPayment::count());
        $this->assertSame(4, Subcontract::count());
    }

    public function test_the_subcontract_show_and_report_pages_render(): void
    {
        $company = $this->seedCompany();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $subcontract = Subcontract::where('title', 'Al Jazeera')->first();

        $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract))->assertOk();
        $response = $this->actingAs($owner)->get(route('app.subcontracts.report.pdf', $subcontract));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
