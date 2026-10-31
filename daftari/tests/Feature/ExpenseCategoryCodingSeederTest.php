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
use App\Services\Accounting\LedgerPostingService;
use Database\Seeders\ExpenseCategoryCodingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "add proper expenses categories with code recommended by zatca and
 * adjust transaction by that way" — codes the 14 real categories
 * (merging the duplicate "Rent" by code, not by deleting it), and
 * reposts the un-overridden Salaries/Maintenance/Fuel/Project
 * transactions to their new category-linked GL account.
 */
class ExpenseCategoryCodingSeederTest extends TestCase
{
    use RefreshDatabase;

    private function seedCompany(): Company
    {
        $company = Company::create(['name' => 'Dynamic Core Contracting Company', 'slug' => 'dcc-'.uniqid(), 'status' => 'active', 'currency' => 'SAR', 'vat_number' => '314526094900003']);
        Account::seedSystemAccounts($company->id);
        AccountMapping::seedDefaults($company->id);

        return $company;
    }

    private function seedCategories(Company $company): void
    {
        foreach (['Office supplies', 'Utilities', 'Rent', 'Bill', 'Management', 'Operating expense', 'Salaries', 'Maintenance', 'Fuel', 'Project', 'Home', 'Home Food', 'VAT'] as $name) {
            ExpenseCategory::create(['company_id' => $company->id, 'name' => $name]);
        }
        // The real duplicate: a second "Rent" row with no expenses of its own.
        ExpenseCategory::create(['company_id' => $company->id, 'name' => 'Rent']);
    }

    private function seedExpense(Company $company, BankAccount $bank, string $categoryName, array $overrides = []): Expense
    {
        $category = ExpenseCategory::where('company_id', $company->id)->where('name', $categoryName)->orderBy('id')->first();

        $expense = Expense::create(array_merge([
            'company_id' => $company->id,
            'expense_category_id' => $category->id,
            'bank_account_id' => $bank->id,
            'description' => "Real {$categoryName} cost",
            'amount' => 1000.00,
            'gross_amount' => 1000.00,
            'vat_amount' => 0,
            'tax_category' => 'zero_rated',
            'status' => 'approved',
            'expense_date' => '2026-10-01',
        ], $overrides));

        app(LedgerPostingService::class)->postExpense($expense->fresh(['account', 'category', 'bankAccount']));

        return $expense;
    }

    public function test_it_does_nothing_when_the_company_does_not_exist(): void
    {
        (new ExpenseCategoryCodingSeeder)->run();

        $this->assertSame(0, ExpenseCategory::count());
    }

    public function test_it_codes_every_real_category_and_groups_them(): void
    {
        $company = $this->seedCompany();
        $this->seedCategories($company);

        (new ExpenseCategoryCodingSeeder)->run();

        $maintenance = ExpenseCategory::where('company_id', $company->id)->where('name', 'Maintenance')->first();
        $this->assertSame('DIRECT-MAINT', $maintenance->code);
        $this->assertTrue((bool) $maintenance->is_direct_cost);
        $this->assertTrue((bool) $maintenance->requires_project);
        $this->assertNotNull($maintenance->parent_category_id);
        $this->assertSame('Direct Project Costs', $maintenance->parent->name);

        $salaries = ExpenseCategory::where('company_id', $company->id)->where('name', 'Salaries')->first();
        $this->assertSame('PAYROLL-SALARY', $salaries->code);
        $linkedAccount = Account::where('company_id', $company->id)->where('code', '5350')->first();
        $this->assertSame($linkedAccount->id, $salaries->linked_account_id);

        $office = ExpenseCategory::where('company_id', $company->id)->where('name', 'Office supplies')->first();
        $this->assertSame('ADMIN-OFFICE', $office->code);
        $this->assertNull($office->linked_account_id);
    }

    public function test_it_codes_the_duplicate_rent_row_and_soft_disables_it(): void
    {
        $company = $this->seedCompany();
        $this->seedCategories($company);

        (new ExpenseCategoryCodingSeeder)->run();

        $rentRows = ExpenseCategory::where('company_id', $company->id)->where('name', 'Rent')->orderBy('id')->get();
        $this->assertCount(2, $rentRows);
        $this->assertSame('ADMIN-RENT', $rentRows[0]->code);
        $this->assertSame('ADMIN-RENT', $rentRows[1]->code);
        $this->assertTrue((bool) $rentRows[0]->is_active);
        $this->assertFalse((bool) $rentRows[1]->is_active);
    }

    public function test_it_reposts_an_unoverridden_maintenance_expense_to_cost_of_goods_sold(): void
    {
        $company = $this->seedCompany();
        $this->seedCategories($company);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Maintenance');

        $operatingExpense = Account::where('company_id', $company->id)->where('code', '5100')->first();
        $journalBefore = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertNotNull(JournalEntryLine::where('journal_entry_id', $journalBefore->id)->where('account_id', $operatingExpense->id)->first(), 'before the fix it posts to the old 5100 default');

        (new ExpenseCategoryCodingSeeder)->run();

        $cogs = Account::where('company_id', $company->id)->where('code', '5000')->first();
        $journalAfter = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
        $this->assertNotNull(JournalEntryLine::where('journal_entry_id', $journalAfter->id)->where('account_id', $cogs->id)->first());
        $this->assertNull(JournalEntryLine::where('journal_entry_id', $journalAfter->id)->where('account_id', $operatingExpense->id)->first());
        $expense->refresh();
        $this->assertNull($expense->account_id, 'the category default applies without ever writing an override onto the expense itself');
    }

    public function test_an_explicit_per_transaction_override_is_never_touched(): void
    {
        $company = $this->seedCompany();
        $this->seedCategories($company);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $drawings = Account::where('company_id', $company->id)->where('code', '3100')->first();
        // A Fuel expense someone already manually reclassified to Owner's Drawings.
        $expense = $this->seedExpense($company, $bank, 'Fuel', ['account_id' => $drawings->id]);

        (new ExpenseCategoryCodingSeeder)->run();

        $journalEntry = JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->first();
        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
        $this->assertNotNull(JournalEntryLine::where('journal_entry_id', $journalEntry->id)->where('account_id', $drawings->id)->first());
    }

    public function test_reseeding_does_not_reclassify_or_repost_twice(): void
    {
        $company = $this->seedCompany();
        $this->seedCategories($company);
        $bank = BankAccount::create(['company_id' => $company->id, 'name' => 'Petty Cash', 'type' => 'cash', 'currency' => 'SAR', 'is_active' => true]);
        $expense = $this->seedExpense($company, $bank, 'Salaries');

        (new ExpenseCategoryCodingSeeder)->run();
        $countAfterFirstRun = ExpenseCategory::where('company_id', $company->id)->count();

        (new ExpenseCategoryCodingSeeder)->run();

        $this->assertSame(1, JournalEntry::where('source_type', 'expense')->where('source_id', $expense->id)->count());
        $this->assertSame($countAfterFirstRun, ExpenseCategory::where('company_id', $company->id)->count(), 'no duplicate group/category rows on a second run');
    }
}
