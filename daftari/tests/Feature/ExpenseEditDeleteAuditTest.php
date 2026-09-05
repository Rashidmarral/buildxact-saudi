<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Audit finding: editing or deleting an already-posted expense left no
 * AuditLog trail at all — unlike approve()/reject() on the same
 * controller, which both record one. Editing also silently hard-deleted
 * the journal entry with no record of what the old figures were.
 */
class ExpenseEditDeleteAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(): User
    {
        $company = Company::create(['name' => 'Ledger Co.', 'slug' => 'ledger-'.uniqid()]);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return User::factory()->create(['role' => 'owner', 'company_id' => $company->id, 'status' => 'active']);
    }

    private function postedExpense(User $owner): Expense
    {
        return Expense::create([
            'company_id' => $owner->company_id, 'gross_amount' => 115, 'amount' => 100, 'vat_amount' => 15,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'approved',
        ]);
    }

    public function test_editing_a_posted_expense_reposts_the_ledger_and_writes_an_audit_entry(): void
    {
        $owner = $this->makeOwner();
        $expense = $this->postedExpense($owner);
        app(\App\Services\Accounting\LedgerPostingService::class)->postExpense($expense);

        $this->actingAs($owner)->put(route('app.expenses.update', $expense), [
            'gross_amount' => 230, 'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
        $entry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertSame(230.0, (float) $entry->lines()->sum('debit'));

        $log = AuditLog::where('action', 'expense.update')->where('subject_id', $expense->id)->first();
        $this->assertNotNull($log);
        $this->assertSame(115.0, (float) $log->old_value['gross_amount']);
        $this->assertSame(230.0, (float) $log->new_value['gross_amount']);
    }

    public function test_deleting_a_posted_expense_reverses_the_ledger_and_writes_an_audit_entry(): void
    {
        $owner = $this->makeOwner();
        $expense = $this->postedExpense($owner);
        app(\App\Services\Accounting\LedgerPostingService::class)->postExpense($expense);
        $expenseId = $expense->id;

        $this->actingAs($owner)->delete(route('app.expenses.destroy', $expense));

        $this->assertNotNull(JournalEntry::where('source_type', 'expense_reversal')->where('source_id', $expenseId)->first());

        $log = AuditLog::where('action', 'expense.delete')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString((string) $expenseId, $log->description);
        $this->assertStringContainsString('reversed', $log->description);
    }

    public function test_editing_an_unapproved_expense_never_touches_the_ledger_but_still_logs(): void
    {
        $owner = $this->makeOwner();
        $expense = Expense::create([
            'company_id' => $owner->company_id, 'gross_amount' => 50, 'amount' => 43.48, 'vat_amount' => 6.52,
            'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(), 'status' => 'pending_approval',
        ]);

        $this->actingAs($owner)->put(route('app.expenses.update', $expense), [
            'gross_amount' => 60, 'tax_category' => 'standard_15', 'expense_date' => now()->toDateString(),
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame(0, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
        $this->assertNotNull(AuditLog::where('action', 'expense.update')->where('subject_id', $expense->id)->first());
    }
}
