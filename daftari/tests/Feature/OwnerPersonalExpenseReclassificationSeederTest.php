<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Database\Seeders\OwnerPersonalExpenseReclassificationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "how seperate the expenses what should i do" — 13 real personal
 * (Home / Home Food) expenses were booked as deductible Operating
 * Expenses. This moves them to a proper Owner's Drawings equity
 * account without touching a single riyal of real cash.
 */
class OwnerPersonalExpenseReclassificationSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function seedHomeExpense(Company $company, BankAccount $bank, array $overrides = []): Expense
    {
        $category = ExpenseCategory::firstOrCreate(['company_id' => $company->id, 'name' => $overrides['category'] ?? 'Home']);

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bank->id,
            'description' => 'Coffee',
            'amount' => 20.00,
            'gross_amount' => 20.00,
            'vat_amount' => 0,
            'tax_category' => 'zero_rated',
            'status' => 'approved',
            'expense_date' => '2026-10-16',
        ], array_diff_key($overrides, ['category' => null])));

        app(\App\Services\Accounting\LedgerPostingService::class)->postExpense($expense->fresh(['account', 'bankAccount']));

        return $expense;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new OwnerPersonalExpenseReclassificationSeeder)->run();

        $this->assertSame(0, Account::where('code', '3100')->count());
    }

    public function test_it_creates_the_owners_drawings_account(): void
    {
        $company = $this->seedCompany();

        (new OwnerPersonalExpenseReclassificationSeeder)->run();

        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();
        $this->assertNotNull($drawings);
        $this->assertSame('equity', $drawings->type);
        $this->assertSame('debit', $drawings->normal_balance);
    }

    public function test_it_reclassifies_a_matching_expense_without_touching_the_cash_side(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Rashid Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedHomeExpense($company, $bank, ['description' => 'Coffee', 'amount' => 20.00, 'gross_amount' => 20.00, 'expense_date' => '2026-10-16']);

        $cashBefore = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('source_type', 'expense')->where('source_id', $expense->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');

        (new OwnerPersonalExpenseReclassificationSeeder)->run();

        $expense->refresh();
        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();
        $this->assertSame($drawings->id, $expense->account_id);

        $journalEntry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull($journalEntry);
        $drawingsLine = JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $drawings->id)->first();
        $this->assertNotNull($drawingsLine);
        $this->assertEqualsWithDelta(20.00, (float) $drawingsLine->debit, 0.01);

        $operatingExpense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $this->assertNull(JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $operatingExpense->id)->first());

        $cashAfter = JournalEntryLine::whereHas('journalEntry', fn ($q) => $q->where('source_type', 'expense')->where('source_id', $expense->id))
            ->whereHas('account', fn ($q) => $q->whereIn('code', ['1000', '1100']))
            ->sum('credit');
        $this->assertEqualsWithDelta((float) $cashBefore, (float) $cashAfter, 0.01);
    }

    public function test_it_leaves_an_unmatched_expense_alone(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Rashid Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        // A real business expense that happens to share a category name — must not be swept up.
        $expense = $this->seedHomeExpense($company, $bank, ['description' => 'Completely unrelated real business cost', 'amount' => 999.00, 'gross_amount' => 999.00, 'expense_date' => '2026-10-16']);

        (new OwnerPersonalExpenseReclassificationSeeder)->run();

        $expense->refresh();
        $this->assertNull($expense->account_id);
    }

    public function test_reseeding_does_not_reclassify_twice(): void
    {
        $company = $this->seedCompany();
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Rashid Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedHomeExpense($company, $bank, ['description' => 'Coffee', 'amount' => 20.00, 'gross_amount' => 20.00, 'expense_date' => '2026-10-16']);

        (new OwnerPersonalExpenseReclassificationSeeder)->run();
        (new OwnerPersonalExpenseReclassificationSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
    }
}
