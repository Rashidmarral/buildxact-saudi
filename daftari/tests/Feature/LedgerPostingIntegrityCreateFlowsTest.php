<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Client;
use App\Models\Company;
use App\Models\CreditNote;
use App\Models\DebitNote;
use App\Models\Employee;
use App\Models\EndOfServiceSettlement;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding GL-03: CreditNoteController::store(), DebitNoteController::
 * store(), PurchaseReturnController::store(), and EmployeeController::
 * terminate() all used to call their LedgerPostingService post*() method
 * AFTER the enclosing DB::transaction() had already committed (or, for
 * terminate(), had no transaction wrapping the status change, settlement
 * record, and posting call together at all). A ledger-posting failure —
 * a missing account mapping, a locked period — left a fully issued,
 * numbered document (or a permanently terminated employee with a
 * settlement on file) with no corresponding journal entry, silently
 * corrupting the books. Each posting call now runs inside the same
 * transaction as the document's creation, mirroring the existing pattern
 * already proven for Bill/Expense in LedgerPostingIntegrityTest.
 *
 * These tests force the posting call to throw (no account mappings
 * seeded) and assert the antecedent record was rolled back too.
 */
class LedgerPostingIntegrityCreateFlowsTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Rollback Co.', 'slug' => 'rollback-co-'.uniqid(), 'status' => 'active']);
        // Deliberately no Account::seedSystemAccounts()/AccountMapping::seedDefaults()
        // — the missing mappings are what makes post*() throw for real.

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_issuing_a_credit_note_rolls_back_if_ledger_posting_fails(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        try {
            $this->actingAs($owner)->post(route('app.credit-notes.store'), [
                'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
                'items' => [['description' => 'Full credit', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 15]],
            ]);
        } catch (\Throwable) {
            // Expected — the posting failure propagates past the controller.
        }

        $this->assertSame(0, CreditNote::count(), 'A ledger-posting failure must roll back the credit note too.');
        $this->assertSame(0, JournalEntry::where('company_id', $owner->company_id)->count());
    }

    public function test_issuing_a_debit_note_rolls_back_if_ledger_posting_fails(): void
    {
        $owner = $this->makeOwner();
        $client = Client::create(['company_id' => $owner->company_id, 'name' => 'Client A']);
        $invoice = Invoice::create([
            'company_id' => $owner->company_id, 'client_id' => $client->id, 'invoice_number' => 'INV-1',
            'type' => 'standard', 'issue_date' => now(), 'due_date' => now()->addDays(30),
            'status' => 'sent', 'currency' => 'SAR', 'subtotal' => 1000, 'vat_total' => 150, 'total' => 1150,
        ]);

        try {
            $this->actingAs($owner)->post(route('app.debit-notes.store'), [
                'invoice_id' => $invoice->id, 'issue_date' => now()->toDateString(),
                'items' => [['description' => 'Under-billed charge', 'quantity' => 1, 'unit_price' => 100, 'vat_rate' => 15]],
            ]);
        } catch (\Throwable) {
            // Expected.
        }

        $this->assertSame(0, DebitNote::count(), 'A ledger-posting failure must roll back the debit note too.');
        $this->assertSame(0, JournalEntry::where('company_id', $owner->company_id)->count());
    }

    public function test_issuing_a_purchase_return_rolls_back_if_ledger_posting_fails(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Supplier A']);
        $bill = Bill::create([
            'company_id' => $owner->company_id, 'supplier_id' => $supplier->id, 'bill_number' => 'BILL-1',
            'status' => 'posted', 'bill_date' => now()->toDateString(), 'currency' => 'SAR',
            'subtotal' => 1000, 'vat_total' => 0, 'total' => 1000,
        ]);
        BillItem::create(['bill_id' => $bill->id, 'description' => 'Items', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0, 'vat_amount' => 0, 'line_total' => 1000]);

        try {
            $this->actingAs($owner)->post(route('app.purchase-returns.store'), [
                'bill_id' => $bill->id, 'issue_date' => now()->toDateString(),
                'items' => [['description' => 'Returned item', 'quantity' => 1, 'unit_price' => 1000, 'vat_rate' => 0]],
            ]);
        } catch (\Throwable) {
            // Expected.
        }

        $this->assertSame(0, PurchaseReturn::count(), 'A ledger-posting failure must roll back the purchase return too.');
        $this->assertSame(0, JournalEntry::where('company_id', $owner->company_id)->count());
    }

    public function test_terminating_an_employee_rolls_back_the_status_change_and_settlement_if_ledger_posting_fails(): void
    {
        $owner = $this->makeOwner();
        $employee = Employee::create([
            'company_id' => $owner->company_id, 'employee_number' => 'EMP-1', 'full_name' => 'Ahmed Ali',
            'hire_date' => now()->subYears(3), 'basic_salary' => 10000, 'status' => 'active', 'is_saudi' => true,
        ]);

        try {
            $this->actingAs($owner)->post(route('app.employees.terminate', $employee), [
                'termination_date' => now()->toDateString(), 'reason' => 'resignation',
            ]);
        } catch (\Throwable) {
            // Expected.
        }

        $employee->refresh();
        $this->assertSame('active', $employee->status, 'A ledger-posting failure must roll back the termination too.');
        $this->assertSame(0, EndOfServiceSettlement::count());
        $this->assertSame(0, JournalEntry::where('company_id', $owner->company_id)->count());
    }

    public function test_terminating_an_employee_with_zero_gratuity_still_commits_with_no_ledger_entry_needed(): void
    {
        $owner = $this->makeOwner();
        // Less than 2 years of service and a resignation reason yields
        // zero gratuity under EndOfServiceCalculator — postEndOfServiceSettlement()
        // is never called, so this must succeed even with no account
        // mappings seeded at all.
        $employee = Employee::create([
            'company_id' => $owner->company_id, 'employee_number' => 'EMP-1', 'full_name' => 'Sara Fahad',
            'hire_date' => now()->subMonths(6), 'basic_salary' => 10000, 'status' => 'active', 'is_saudi' => true,
        ]);

        $this->actingAs($owner)->post(route('app.employees.terminate', $employee), [
            'termination_date' => now()->toDateString(), 'reason' => 'resignation',
        ])->assertRedirect();

        $employee->refresh();
        $this->assertSame('terminated', $employee->status);
        $settlement = EndOfServiceSettlement::first();
        $this->assertNotNull($settlement);
        $this->assertEqualsWithDelta(0, $settlement->gratuity_amount, 0.01);
    }
}
