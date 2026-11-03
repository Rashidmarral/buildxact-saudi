<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Company;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\SubcontractPayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug report: "Attempt to read property 'voucher_number' on null" on a
 * subcontract's show page. payment_vouchers uses SoftDeletes, so
 * deleting a voucher never fires the subcontract_payments.payment_voucher_id
 * foreign key's nullOnDelete() (a real-DELETE-only trigger) — the row
 * just gets deleted_at set, and Eloquent's default (non-trashed) query
 * can no longer find it, so ->paymentVoucher resolves to null while
 * payment_voucher_id stays set.
 */
class SubcontractPaymentOrphanedVoucherLinkTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): array
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR']);
        $owner = User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);

        return [$company, $owner];
    }

    private function makeSubcontractWithLinkedVoucher(Company $company, User $owner): array
    {
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

        $this->actingAs($owner)->post(route('app.subcontracts.link-voucher', $subcontract), ['payment_voucher_id' => [$voucher->id]]);

        return [$subcontract, $voucher];
    }

    public function test_deleting_the_linked_voucher_unlinks_it_from_the_subcontract_payment_instead_of_leaving_a_dangling_reference(): void
    {
        [$company, $owner] = $this->makeOwner();
        [$subcontract, $voucher] = $this->makeSubcontractWithLinkedVoucher($company, $owner);

        $voucher->update(['status' => 'void']);
        $this->actingAs($owner)->delete(route('app.payment-vouchers.destroy', $voucher));

        $this->assertNull(SubcontractPayment::first()->payment_voucher_id, 'Deleting the voucher must null the subcontract payment\'s link, not leave it dangling.');

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract));
        $response->assertOk();
        $response->assertSee(__('Manual entry'));
    }

    public function test_the_show_page_does_not_crash_on_a_pre_existing_dangling_reference(): void
    {
        // Simulates data already broken by a voucher deleted BEFORE this
        // fix existed — no controller action nulled the link, so
        // payment_voucher_id is still set to a now-trashed voucher's id.
        [$company, $owner] = $this->makeOwner();
        [$subcontract, $voucher] = $this->makeSubcontractWithLinkedVoucher($company, $owner);

        $voucher->update(['status' => 'void']);
        $voucher->delete();
        $this->assertNotNull(SubcontractPayment::first()->payment_voucher_id, 'Soft-deleting the voucher directly (bypassing the destroy route) must leave the dangling reference this test exists to guard against.');

        $response = $this->actingAs($owner)->get(route('app.subcontracts.show', $subcontract));
        $response->assertOk();
        $response->assertSee(__('Deleted voucher'));
    }

    public function test_the_catchup_migration_clears_dangling_references_without_touching_valid_ones(): void
    {
        [$company, $owner] = $this->makeOwner();
        [$subcontract, $voucher] = $this->makeSubcontractWithLinkedVoucher($company, $owner);

        // A second, still-valid linked payment to prove the migration is
        // selective, not a blanket wipe.
        $validVoucher = PaymentVoucher::create([
            'company_id' => $company->id, 'bank_account_id' => $voucher->bank_account_id, 'supplier_id' => $voucher->supplier_id, 'project_id' => $voucher->project_id,
            'voucher_number' => 'PAY-00002', 'date' => now(), 'payee_name' => $voucher->payee_name, 'amount' => 25000,
            'method' => 'bank_transfer', 'status' => 'issued',
        ]);
        $subcontract->payments()->create(['company_id' => $company->id, 'payment_voucher_id' => $validVoucher->id, 'amount' => 25000, 'paid_at' => now()]);

        $voucher->update(['status' => 'void']);
        $voucher->delete();

        (require base_path('database/migrations/2026_11_24_010000_unlink_subcontract_payments_from_deleted_vouchers.php'))->up();

        $this->assertNull(SubcontractPayment::where('amount', 50000)->first()->payment_voucher_id, 'The migration must clear a reference to a soft-deleted voucher.');
        $this->assertEquals($validVoucher->id, SubcontractPayment::where('amount', 25000)->first()->payment_voucher_id, 'The migration must leave a reference to a voucher that still exists untouched.');
    }
}
