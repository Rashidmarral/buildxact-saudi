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
 * "make a report something like this 110800 cash we have paid to
 * shahid shows that then subbase we have given them on 73000 same
 * asphalt transportation something like this" — a free-text,
 * user-defined breakdown of a subcontract's own payments by cost
 * category (not by milestone), same group_label[]/group_terms[]
 * mechanism the Payment Vouchers grouped summary already uses.
 */
class SubcontractCategoryBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubcontractWithPayments(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-1', 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'SAED EST']);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Main Account', 'type' => 'bank', 'is_active' => true]);

        $subcontract = Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'supplier_id' => $supplier->id,
            'contract_value' => 200000, 'retention_percent' => 5, 'status' => 'active',
        ]);
        $subcontract->milestones()->create(['sequence' => 1, 'name' => 'Full', 'percentage' => 100, 'amount' => 200000]);

        $makeVoucher = function (string $number, float $amount, string $reference) use ($company, $bank, $supplier) {
            return PaymentVoucher::create([
                'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $supplier->id,
                'voucher_number' => $number, 'date' => now(), 'payee_name' => 'SAED EST', 'amount' => $amount,
                'method' => 'bank_transfer', 'reference' => $reference, 'status' => 'issued',
            ]);
        };

        $shahid1 = $makeVoucher('PV-1', 5000, 'Payment to Shahid Sir for project works');
        $shahid2 = $makeVoucher('PV-2', 5800, 'Payment to Shahid Sir for project works');
        $machine = $makeVoucher('PV-3', 11600, 'Machine Rent and Lobed charges');

        foreach ([$shahid1, $shahid2, $machine] as $voucher) {
            $subcontract->payments()->create([
                'company_id' => $company->id, 'milestone_id' => $subcontract->milestones->first()->id,
                'payment_voucher_id' => $voucher->id, 'amount' => $voucher->amount, 'paid_at' => $voucher->date,
                'method' => $voucher->method, 'reference' => $voucher->reference,
            ]);
        }

        return [$owner, $subcontract];
    }

    public function test_the_show_page_groups_payments_by_user_defined_category(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', [
            'subcontract' => $subcontract,
            'group_label' => ['Cash/Transfer', 'Machine'],
            'group_terms' => ['shahid', 'machine'],
        ]));

        $response->assertOk();
        $response->assertSee('Cash/Transfer');
        $response->assertSee('10,800.00');
        $response->assertSee('Machine');
        $response->assertSee('11,600.00');
    }

    public function test_unmatched_payments_fall_into_other_unmatched(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', [
            'subcontract' => $subcontract,
            'group_label' => ['Cash/Transfer'],
            'group_terms' => ['shahid'],
        ]));

        $response->assertOk();
        $response->assertSee('Other / Unmatched');
        $response->assertSee('11,600.00');
    }

    public function test_the_category_breakdown_pdf_downloads(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $response = $this->actingAs($owner)->get(route('app.subcontracts.category-report.pdf', [
            'subcontract' => $subcontract,
            'group_label' => ['Cash/Transfer', 'Machine'],
            'group_terms' => ['shahid', 'machine'],
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_grand_total_always_equals_the_subcontracts_own_net_total(): void
    {
        [$owner, $subcontract] = $this->makeSubcontractWithPayments();

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', [
            'subcontract' => $subcontract,
            'group_label' => ['Cash/Transfer'],
            'group_terms' => ['shahid'],
        ]));

        $response->assertOk();
        // 10,800 (Cash/Transfer) + 11,600 (Other/Unmatched) = 22,400 = totalPaid().
        $this->assertEqualsWithDelta(22400.0, $subcontract->totalPaid(), 0.01);
        $response->assertSee('22,400.00');
    }
}
