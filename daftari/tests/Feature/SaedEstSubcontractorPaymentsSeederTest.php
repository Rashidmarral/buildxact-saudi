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
 * "this my db take payment data from this date description etc to add
 * it subcontractor payment my company id is 1 dynamic core contracting
 * company" — the operator's own database export showed the real
 * Subcontract they'd already created themselves through the UI (ONE
 * contract, "Subbase, Base Course and Asphalt work", SAR 373,650.00,
 * the standard 20/30/30/15/5% schedule) with no payments linked yet.
 * This seeder links all 28 real SAED EST vouchers into that one real
 * contract, through the normal voucher/GL-posting path.
 */
class SaedEstSubcontractorPaymentsSeederTest extends TestCase
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
            ['sequence' => 1, 'name' => 'Advance', 'percentage' => 20, 'amount' => 74730.00, 'requires_handover_approval' => false],
            ['sequence' => 2, 'name' => 'Milestone 2', 'percentage' => 30, 'amount' => 112095.00, 'requires_handover_approval' => false],
            ['sequence' => 3, 'name' => 'Milestone 3', 'percentage' => 30, 'amount' => 112095.00, 'requires_handover_approval' => false],
            ['sequence' => 4, 'name' => 'Milestone 4', 'percentage' => 15, 'amount' => 56047.50, 'requires_handover_approval' => false],
            ['sequence' => 5, 'name' => 'Final (after handover & approval)', 'percentage' => 5, 'amount' => 18682.50, 'requires_handover_approval' => true],
        ] as $m) {
            $subcontract->milestones()->create($m);
        }

        return [$company, $subcontract];
    }

    public function test_it_errors_cleanly_and_writes_nothing_when_no_matching_subcontract_exists(): void
    {
        Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $this->assertSame(0, PaymentVoucher::count());
        $this->assertSame(0, SubcontractPayment::count());
    }

    public function test_it_links_every_real_voucher_into_the_one_real_subcontract_through_the_gl(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $this->assertSame(28, PaymentVoucher::where('company_id', $company->id)->count());
        $this->assertSame(28, JournalEntry::where('company_id', $company->id)->where('source_type', 'payment_voucher')->count());
        // >= 28, not ===: a voucher that straddles a milestone boundary
        // waterfalls into 2 rows, same as a real link through the controller.
        $this->assertGreaterThanOrEqual(28, SubcontractPayment::where('company_id', $company->id)->count());
        $this->assertSame(1, Subcontract::where('company_id', $company->id)->count(), 'no second contract should be created');

        // The real PDF/report total, exact — every voucher's own gross amount.
        $this->assertEqualsWithDelta(352065.60, $subcontract->fresh()->totalReceivedGross(), 0.01);

        // Net applied is strictly less than gross (VAT corrections reduced it)
        // and never exceeds the contract value.
        $totalPaid = $subcontract->fresh()->totalPaid();
        $this->assertLessThan(352065.60, $totalPaid);
        $this->assertLessThanOrEqual(373650.00, $totalPaid);

        // The milestone ledger and the subcontract's own net total always agree.
        $milestoneSum = $subcontract->milestones()->get()->sum(fn ($m) => $m->paidAmount())
            + SubcontractPayment::where('subcontract_id', $subcontract->id)->whereNull('milestone_id')->sum('amount');
        $this->assertEqualsWithDelta($totalPaid, $milestoneSum, 0.02);

        // The first (lowest-sequence) milestone, SAR 74,730, is filled
        // first in chronological order and should be fully funded.
        $firstMilestone = $subcontract->milestones()->orderBy('sequence')->first();
        $this->assertEqualsWithDelta(74730.00, $firstMilestone->paidAmount(), 0.02);
    }

    public function test_the_al_jazeera_and_asphalt_groups_apply_the_confirmed_vat_corrections(): void
    {
        [$company] = $this->seedCompanyWithRealSubcontract();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $alJazeeraNet = SubcontractPayment::whereHas('paymentVoucher', fn ($q) => $q->whereIn('voucher_number', ['PV-00013', 'PV-00016', 'PV-00019', 'PV-00021', 'PV-00022']))->sum('amount');
        $this->assertEqualsWithDelta(31100.00 / 1.15, $alJazeeraNet, 0.05);

        $asphaltNet = SubcontractPayment::whereHas('paymentVoucher', fn ($q) => $q->whereIn('voucher_number', ['PV-00029', 'PV-00032', 'PV-00033']))->sum('amount');
        $this->assertEqualsWithDelta(107352.20, $asphaltNet, 0.05);

        // Real voucher amounts are untouched by the VAT correction.
        $this->assertEqualsWithDelta(31100.00, PaymentVoucher::whereIn('voucher_number', ['PV-00013', 'PV-00016', 'PV-00019', 'PV-00021', 'PV-00022'])->sum('amount'), 0.01);
        $this->assertEqualsWithDelta(122665.60, PaymentVoucher::whereIn('voucher_number', ['PV-00029', 'PV-00032', 'PV-00033'])->sum('amount'), 0.01);
    }

    public function test_reseeding_does_not_duplicate_vouchers_or_links(): void
    {
        $this->seedCompanyWithRealSubcontract();

        (new SaedEstSubcontractorPaymentsSeeder)->run();
        $paymentRowsAfterFirstRun = SubcontractPayment::count();

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $this->assertSame(28, PaymentVoucher::count());
        $this->assertSame(28, JournalEntry::where('source_type', 'payment_voucher')->count());
        $this->assertSame($paymentRowsAfterFirstRun, SubcontractPayment::count());
    }

    public function test_the_subcontract_show_and_report_pages_render(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract))->assertOk();
        $response = $this->actingAs($owner)->get(route('app.subcontracts.report.pdf', $subcontract));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    /**
     * "because ashraf 7700 is trailer rent and one 5000 net cash paid
     * by us that is payable of shahid and that 5000 is include in
     * 110800 in shahid payment" — the default category breakdown must
     * pull the MC1 voucher and the one reclassified SAR 5,000 "Ashraf"
     * voucher out correctly despite their text also containing
     * "Shahid"/"trailer rent", and the grand total must always
     * reconcile exactly to the contract's own net total — nothing lost,
     * nothing double-counted.
     */
    public function test_the_default_category_breakdown_reclassifies_the_two_ashraf_vouchers_correctly(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new SaedEstSubcontractorPaymentsSeeder)->run();

        $defaults = [
            'MC1' => 'mc1', 'Cash/Transfer' => 'shahid,net cash paid to ashraf',
            'Asphalt Transportation' => 'trailer rent', 'Sub Base' => 'ashraf,gazaierih',
            'Asphalt' => 'anajim', 'Machine' => 'machine',
        ];

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', [
            'subcontract' => $subcontract, 'group_label' => array_keys($defaults), 'group_terms' => array_values($defaults),
        ]));

        $response->assertOk();
        $response->assertSee('4,000.00'); // MC1 kept separate from Cash/Transfer.
        $response->assertSee('115,800.00'); // Cash/Transfer: 110,800 Shahid-named + the reclassified 5,000.
        $response->assertSee('7,700.00'); // Asphalt Transportation: the one real trailer-rent voucher.
        $response->assertSee('76,043.47'); // Sub Base: Ashraf's remaining 49,000 + Al Jazeera net.

        $totalPaid = $subcontract->fresh()->totalPaid();
        $response->assertSee(number_format($totalPaid, 2));
    }

    /**
     * "shahid payment vouchers 124800 shows detail wise in report
     * other alhazera and ashraf payment shows as Sub Base 146.00 trip
     * 500.00 unit price 73,000.00 total... asphalt transportation
     * 17900 of 28 trucks... asphalt machine rent 11600... mubashir and
     * khalid 800" — the seeder's own stored report categories (not the
     * ad-hoc, query-driven ones) must reproduce these exact figures
     * against the real data, and Saad Riman's one real, still-unmatched
     * voucher must not disappear or get double-counted.
     */
    public function test_the_seeded_report_categories_reproduce_the_operators_exact_figures(): void
    {
        [$company, $subcontract] = $this->seedCompanyWithRealSubcontract();
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        (new SaedEstSubcontractorPaymentsSeeder)->run();
        (new \Database\Seeders\ShahidTransferOct29Seeder)->run();

        $controller = app(\App\Http\Controllers\User\SubcontractController::class);
        $this->actingAs($owner);
        $method = new \ReflectionMethod($controller, 'resolveReportCategories');
        $method->setAccessible(true);
        $rows = collect($method->invoke($controller, $subcontract->fresh()))->keyBy(fn ($r) => $r['category']->label);

        $this->assertEqualsWithDelta(124800.00, $rows['Shahid']['total'], 0.01);
        $this->assertEqualsWithDelta(73000.00, $rows['Sub Base']['total'], 0.01);
        $this->assertEqualsWithDelta(107352.20, $rows['Asphalt']['total'], 0.01);
        $this->assertEqualsWithDelta(17900.00, $rows['Asphalt Transportation']['total'], 0.01);
        $this->assertEqualsWithDelta(11600.00, $rows['Machine']['total'], 0.01);
        $this->assertEqualsWithDelta(800.00, $rows['Mubashir and Khalid']['total'], 0.01);
        $this->assertEqualsWithDelta(10200.00, $rows['Other / Unmatched']['total'], 0.01);

        $response = $this->actingAs($owner)->get(route('app.subcontracts.report.pdf', $subcontract));
        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }
}
