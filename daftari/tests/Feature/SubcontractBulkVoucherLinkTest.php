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
 * "please add functionality i can add multiple payment vouchers or
 * multiselect vouchers at a time also deleting vouchers should be
 * multiple not a just single remove link" — bulk link-voucher and bulk
 * destroyPayments, exercised through both the subcontracts route and
 * its project-manager-budgets twin (same controller, different scope).
 */
class SubcontractBulkVoucherLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeSubcontract(Company $company): Subcontract
    {
        $project = Project::create(['company_id' => $company->id, 'code' => 'PROJ-'.uniqid(), 'name' => 'Jumum Yard Project', 'status' => 'active']);
        $supplier = Supplier::create(['company_id' => $company->id, 'name' => 'Al Fahad Subcontracting']);

        return Subcontract::create([
            'company_id' => $company->id, 'project_id' => $project->id, 'party_type' => 'supplier', 'supplier_id' => $supplier->id,
            'contract_value' => 373650, 'retention_percent' => 5, 'status' => 'active',
        ]);
    }

    private function makeVoucher(Company $company, Subcontract $subcontract, string $number, float $amount): PaymentVoucher
    {
        $bank = BankAccount::firstOrCreate(
            ['company_id' => $company->id, 'name' => 'Main Account'],
            ['type' => 'bank', 'is_active' => true]
        );

        return PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $bank->id, 'supplier_id' => $subcontract->supplier_id, 'project_id' => $subcontract->project_id,
            'voucher_number' => $number, 'date' => now(), 'payee_name' => 'Al Fahad Subcontracting', 'amount' => $amount,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);
    }

    public function test_several_vouchers_can_be_linked_in_a_single_request(): void
    {
        [$company, $owner] = $this->makeOwner();
        $subcontract = $this->makeSubcontract($company);
        $v1 = $this->makeVoucher($company, $subcontract, 'PAY-1', 50000);
        $v2 = $this->makeVoucher($company, $subcontract, 'PAY-2', 30000);
        $v3 = $this->makeVoucher($company, $subcontract, 'PAY-3', 20000);

        $response = $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), [
            'payment_voucher_id' => [$v1->id, $v2->id, $v3->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('subcontract_payments', 3);
        $this->assertEquals(100000.0, $subcontract->totalPaid());
    }

    public function test_an_already_linked_voucher_in_the_batch_is_skipped_without_failing_the_rest(): void
    {
        [$company, $owner] = $this->makeOwner();
        $subcontract = $this->makeSubcontract($company);
        $v1 = $this->makeVoucher($company, $subcontract, 'PAY-1', 50000);
        $v2 = $this->makeVoucher($company, $subcontract, 'PAY-2', 30000);

        $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), ['payment_voucher_id' => [$v1->id]]);
        $this->assertDatabaseCount('subcontract_payments', 1);

        $response = $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), [
            'payment_voucher_id' => [$v1->id, $v2->id],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseCount('subcontract_payments', 2);
        $this->assertEquals(80000.0, $subcontract->totalPaid());
    }

    public function test_net_amount_override_is_ignored_when_more_than_one_voucher_is_submitted(): void
    {
        [$company, $owner] = $this->makeOwner();
        $subcontract = $this->makeSubcontract($company);
        $v1 = $this->makeVoucher($company, $subcontract, 'PAY-1', 50000);
        $v2 = $this->makeVoucher($company, $subcontract, 'PAY-2', 30000);

        $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), [
            'payment_voucher_id' => [$v1->id, $v2->id], 'net_amount' => 1000,
        ]);

        $this->assertEquals(80000.0, $subcontract->totalPaid(), 'net_amount must only apply when exactly one voucher is linked.');
    }

    public function test_multiple_payments_can_be_bulk_removed_from_the_tracker_in_one_request(): void
    {
        [$company, $owner] = $this->makeOwner();
        $subcontract = $this->makeSubcontract($company);
        $p1 = $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 1000, 'paid_at' => now()]);
        $p2 = $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 2000, 'paid_at' => now()]);
        $p3 = $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 3000, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->delete(route('app.subcontracts.payments.destroy-many', $subcontract), [
            'payment_id' => [$p1->id, $p2->id],
        ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('subcontract_payments', ['id' => $p1->id]);
        $this->assertSoftDeleted('subcontract_payments', ['id' => $p2->id]);
        $this->assertDatabaseHas('subcontract_payments', ['id' => $p3->id, 'deleted_at' => null]);
    }

    public function test_bulk_removal_rejects_a_payment_id_belonging_to_another_subcontract(): void
    {
        [$company, $owner] = $this->makeOwner();
        $subcontract = $this->makeSubcontract($company);
        $otherSubcontract = $this->makeSubcontract($company);
        $ownPayment = $subcontract->payments()->create(['company_id' => $company->id, 'amount' => 1000, 'paid_at' => now()]);
        $foreignPayment = $otherSubcontract->payments()->create(['company_id' => $company->id, 'amount' => 5000, 'paid_at' => now()]);

        $response = $this->actingAs($owner)->delete(route('app.subcontracts.payments.destroy-many', $subcontract), [
            'payment_id' => [$ownPayment->id, $foreignPayment->id],
        ]);

        $response->assertSessionHasErrors('payment_id.1');
        $this->assertDatabaseHas('subcontract_payments', ['id' => $ownPayment->id]);
        $this->assertDatabaseHas('subcontract_payments', ['id' => $foreignPayment->id]);
    }

    public function test_bulk_link_voucher_route_is_scoped_to_employee_budgets_for_project_manager_budgets(): void
    {
        [$company, $owner] = $this->makeOwner();
        $subcontract = $this->makeSubcontract($company);

        $response = $this->actingAs($owner)->post(route('app.project-manager-budgets.link-voucher', $subcontract), [
            'payment_voucher_id' => [1],
        ]);

        $response->assertNotFound();
    }
}
