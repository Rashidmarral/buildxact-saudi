<?php

namespace Tests\Feature;

use App\Models\Bill;
use App\Models\Company;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Accounting\LedgerPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Commercial audit finding C1 (accounting integrity): every controller
 * action that changes a business record's status alongside posting its
 * journal entry (Bill::post()/void(), Expense::store()/update()/destroy()/
 * approve(), and their equivalents on bank transfers, customs
 * declarations, fixed assets, payment/receipt vouchers, purchase returns,
 * and stock adjustments) now wraps both operations in one DB::transaction
 * — previously they were two separate auto-committing statements, so a
 * failure in the ledger call after the status change had already
 * committed left a record marked posted/approved with no matching GL
 * entry, silently corrupting the books.
 *
 * These tests force LedgerPostingService to throw mid-flow and assert the
 * antecedent status change was rolled back too — proving the two writes
 * are now genuinely atomic, not just sequentially likely to succeed
 * together. Only Bill and Expense are covered directly here (the two
 * flows named explicitly in the audit); the same transaction-wrapping
 * pattern was applied identically across the other controllers above.
 */
class LedgerPostingIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Test Co', 'slug' => 'test-co-'.uniqid(), 'status' => 'active']);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    public function test_bill_post_rolls_back_the_status_change_if_ledger_posting_fails(): void
    {
        $owner = $this->makeOwner();
        $supplier = Supplier::create(['company_id' => $owner->company_id, 'name' => 'Test Supplier']);
        $bill = Bill::create([
            'company_id' => $owner->company_id,
            'supplier_id' => $supplier->id,
            'bill_number' => 'BILL-'.uniqid(),
            'status' => 'draft',
            'bill_date' => now()->toDateString(),
            'subtotal' => 100,
            'vat_total' => 15,
            'total' => 115,
        ]);

        $this->mock(LedgerPostingService::class, function ($mock) {
            $mock->shouldReceive('postBillPosted')->once()->andThrow(new \RuntimeException('simulated posting failure'));
        });

        try {
            $this->actingAs($owner)->post(route('app.bills.post', $bill));
        } catch (\RuntimeException) {
            // The exception propagating past the controller is expected
            // here (no try/catch around the transaction in the
            // controller) — what matters is what did/didn't commit.
        }

        $bill->refresh();
        $this->assertSame('draft', $bill->status);
        $this->assertSame(0, JournalEntry::where('company_id', $owner->company_id)->count());
    }

    public function test_expense_approve_rolls_back_the_status_change_if_ledger_posting_fails(): void
    {
        $owner = $this->makeOwner();
        $expense = Expense::create([
            'company_id' => $owner->company_id,
            'vendor_name' => 'Test Vendor',
            'description' => 'Office supplies',
            'amount' => 100,
            'gross_amount' => 115,
            'vat_amount' => 15,
            'expense_date' => now()->toDateString(),
            'status' => 'pending_approval',
        ]);

        $this->mock(LedgerPostingService::class, function ($mock) {
            $mock->shouldReceive('postExpense')->once()->andThrow(new \RuntimeException('simulated posting failure'));
        });

        try {
            $this->actingAs($owner)->post(route('app.expenses.approve', $expense));
        } catch (\RuntimeException) {
            // Same reasoning as above.
        }

        $expense->refresh();
        $this->assertSame('pending_approval', $expense->status);
        $this->assertNull($expense->approved_at);
        $this->assertSame(0, JournalEntry::where('company_id', $owner->company_id)->count());
    }
}
