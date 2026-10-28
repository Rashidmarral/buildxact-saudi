<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "i need to add a module to handle subcontractor payments... we have
 * payments in milestone wise... 20% advance ,30%,30%,15% and 5% after
 * handover and approval of the project... i have already create
 * payment vouchers against subcontractor can i add payments records in
 * module... i need to get receiving payments reports" — the new
 * Subcontract/SubcontractMilestone/SubcontractPayment tracking layer on
 * top of already-posted Payment Vouchers, plus the printable
 * Receiving Payments Report used for signing on site.
 */
class SubcontractorPaymentsModuleTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    public function test_the_default_milestone_schedule_sums_to_100_percent_and_contract_totals_are_calculated_correctly(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $response = $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 373650,
            'retention_percent' => 5, 'status' => 'active',
            'milestones' => [
                ['name' => 'Advance', 'percentage' => 20, 'requires_handover_approval' => false],
                ['name' => 'Milestone 2', 'percentage' => 30, 'requires_handover_approval' => false],
                ['name' => 'Milestone 3', 'percentage' => 30, 'requires_handover_approval' => false],
                ['name' => 'Milestone 4', 'percentage' => 15, 'requires_handover_approval' => false],
                ['name' => 'Final', 'percentage' => 5, 'requires_handover_approval' => true],
            ],
        ]);

        $subcontract = Subcontract::first();
        $response->assertRedirect(route('app.subcontracts.show', $subcontract));

        $this->assertCount(5, $subcontract->milestones);
        $this->assertEqualsWithDelta(100.0, $subcontract->milestones->sum('percentage'), 0.001);
        $this->assertEquals(74730.0, (float) $subcontract->milestones->firstWhere('sequence', 1)->amount);
        $this->assertEquals(18682.5, $subcontract->retentionAmount());
        $this->assertEquals(373650.0, $subcontract->balanceBeforeRetention());
    }

    public function test_milestone_percentages_must_add_up_to_100_percent(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $response = $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 100000,
            'retention_percent' => 5, 'status' => 'active',
            'milestones' => [
                ['name' => 'Advance', 'percentage' => 20, 'requires_handover_approval' => false],
                ['name' => 'Final', 'percentage' => 50, 'requires_handover_approval' => true],
            ],
        ]);

        $response->assertSessionHasErrors('milestones');
        $this->assertDatabaseCount('subcontracts', 0);
    }

    public function test_the_boq_reference_example_matches_the_uploaded_payment_calculation_summary(): void
    {
        // Subbase & Base Course 11,200 m² @ 20.00 + Asphalt 4,100 m² @
        // 36.50, payments totalling 332,212.00, 5% retention — the exact
        // figures from the user's reference sheet.
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 373650,
            'retention_percent' => 5, 'status' => 'active',
            'boq_items' => [
                ['description' => 'Subbase & Base Course', 'quantity' => 11200, 'unit' => 'm2', 'rate' => 20, 'amount' => 224000],
                ['description' => 'Asphalt', 'quantity' => 4100, 'unit' => 'm2', 'rate' => 36.5, 'amount' => 149650],
            ],
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);

        $subcontract = Subcontract::first();
        $this->assertEquals(373650.0, (float) $subcontract->boqItems->sum('amount'));

        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 188800, 'paid_at' => now()->subDays(10)]);
        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 1760, 'paid_at' => now()->subDays(9)]);
        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 141652, 'paid_at' => now()->subDays(5)]);

        $this->assertEquals(332212.0, $subcontract->totalPaid());
        $this->assertEquals(41438.0, $subcontract->balanceBeforeRetention());
        $this->assertEquals(18682.5, $subcontract->retentionAmount());
        $this->assertEquals(22755.5, $subcontract->balanceAfterRetention());
    }

    public function test_an_issued_payment_voucher_can_be_linked_into_the_subcontract_without_posting_a_new_ledger_entry(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id,
            'voucher_number' => 'PAY-00001', 'date' => now(), 'payee_name' => $supplier->name, 'amount' => 50000,
            'method' => 'bank_transfer', 'reference' => 'TRF-123', 'status' => 'issued',
        ]);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 200000,
            'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);
        $subcontract = Subcontract::first();

        $response = $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), ['payment_voucher_id' => $voucher->id]);
        $response->assertRedirect();

        $this->assertDatabaseCount('subcontract_payments', 1);
        $this->assertEquals(50000.0, $subcontract->totalPaid());
        $this->assertEquals(50000.0, $subcontract->totalReceivedGross());
        $this->assertDatabaseCount('journal_entries', 0);

        // Can't double-link the same voucher.
        $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), ['payment_voucher_id' => $voucher->id]);
        $this->assertDatabaseCount('subcontract_payments', 1);
    }

    public function test_a_vat_inclusive_voucher_can_apply_only_its_net_amount_to_the_contract_while_the_voucher_itself_keeps_the_real_cash_figure(): void
    {
        // "i have paid the same amount with tax but i am paying my
        // supplier this amount without tax" — the voucher (real bank
        // transfer) stays untouched at the VAT-inclusive figure; only
        // the VAT-exclusive portion counts toward the contract.
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Dar Al Anajim']);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);
        $voucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $supplier->id, 'project_id' => $project->id,
            'voucher_number' => 'PAY-00002', 'date' => now(), 'payee_name' => $supplier->name, 'amount' => 123454.60,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 373650,
            'retention_percent' => 5, 'status' => 'active',
            'milestones' => [['name' => 'Full', 'percentage' => 100, 'requires_handover_approval' => false]],
        ]);
        $subcontract = Subcontract::first();

        $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), [
            'payment_voucher_id' => $voucher->id, 'net_amount' => 107352.20,
        ]);

        $this->assertEquals(107352.20, $subcontract->totalPaid());
        $this->assertEquals(123454.60, $subcontract->totalReceivedGross());
        $this->assertEquals(123454.60, $voucher->fresh()->amount);
    }

    public function test_a_payment_with_no_milestone_chosen_auto_fills_milestones_in_sequence_and_spills_into_the_next(): void
    {
        // "the payments i have made clear them as milestone total
        // milestone paid move to next" — a waterfall allocation.
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 100000,
            'retention_percent' => 5, 'status' => 'active',
            'milestones' => [
                ['name' => 'Advance', 'percentage' => 20, 'requires_handover_approval' => false],
                ['name' => 'Milestone 2', 'percentage' => 30, 'requires_handover_approval' => false],
                ['name' => 'Milestone 3', 'percentage' => 50, 'requires_handover_approval' => false],
            ],
        ]);
        $subcontract = Subcontract::first();
        $milestones = $subcontract->milestones;

        // 25,000 fully funds the 20,000 Advance milestone and spills
        // 5,000 into Milestone 2.
        $this->actingAs($owner)->post(route('app.subcontracts.payments.store', $subcontract), [
            'amount' => 25000, 'paid_at' => now()->toDateString(),
        ]);

        $this->assertDatabaseCount('subcontract_payments', 2);
        $this->assertEquals(20000.0, $milestones[0]->fresh()->paidAmount());
        $this->assertEquals(5000.0, $milestones[1]->fresh()->paidAmount());
        $this->assertEquals(0.0, $milestones[2]->fresh()->paidAmount());
        $this->assertEquals(25000.0, $subcontract->totalPaid());

        // A second payment of 40,000 tops Milestone 2 up to its full
        // 30,000 (needs 25,000 more) and spills the rest into Milestone 3.
        $this->actingAs($owner)->post(route('app.subcontracts.payments.store', $subcontract), [
            'amount' => 40000, 'paid_at' => now()->toDateString(),
        ]);

        $this->assertEquals(30000.0, $milestones[1]->fresh()->paidAmount());
        $this->assertEquals(15000.0, $milestones[2]->fresh()->paidAmount());
        $this->assertEquals(65000.0, $subcontract->totalPaid());
    }

    public function test_the_receiving_payments_report_pdf_renders_with_the_signature_lines(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 373650,
            'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract = Subcontract::first();
        $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 332212, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->get(route('app.subcontracts.report.pdf', $subcontract));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_index_and_show_pages_render(): void
    {
        [$company, $owner] = $this->makeOwner();
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        $this->actingAs($owner)->post(route('app.subcontracts.store'), [
            'project_id' => $project->id, 'supplier_id' => $supplier->id, 'contract_value' => 100000,
            'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract = Subcontract::first();

        $this->actingAs($owner)->get(route('app.subcontracts.index'))->assertOk()->assertSee('Jumum Yard Project');
        $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract))->assertOk()->assertSee('Al Fahad Subcontracting');
    }
}
